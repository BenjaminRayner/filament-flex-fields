<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @mixin UserSelect
 */
trait ConfiguresUserSelectModel
{
    protected string|Closure|null $userModel = null;

    protected ?Closure $modifyQueryUsing = null;

    protected bool $modelBindingsConfigured = false;

    protected ?Closure $defaultRelationshipRecordResolver = null;

    public function optionModel(string|Closure $model): static
    {
        $this->userModel = $model;

        $this->configureModelBindingsIfNeeded();

        return $this;
    }

    public function query(Closure $query): static
    {
        $this->modifyQueryUsing = $query;

        return $this;
    }

    public function relationship(string|Closure|null $name = null, string|Closure|null $titleAttribute = null, ?Closure $modifyQueryUsing = null, bool $ignoreRecord = false): static
    {
        parent::relationship($name, $titleAttribute, $modifyQueryUsing, $ignoreRecord);
        $this->defaultRelationshipRecordResolver = $this->getSelectedRecordUsing;

        if ($titleAttribute !== null) {
            $this->nameColumn($titleAttribute);
        }

        $this->getOptionLabelFromRecordUsing(function (UserSelect $component, Model $record): array {
            return $component->recordToOptionArray($record);
        });

        $this->getOptionLabelUsing(function (UserSelect $component): ?string {
            $record = $component->getSelectedRecord();

            if (! $record instanceof Model) {
                return null;
            }

            return $component->renderUserOption(
                $component->recordToOptionArray($record),
                layout: 'list',
            );
        });

        $this->getOptionLabelsUsing(function (UserSelect $component, array $values): array {
            return $component->resolveOptionLabelsForValues($values);
        });

        return $this;
    }

    /** Native editing receives a full record under the same scope as search and labels. */
    public function getSelectedRecord(): ?Model
    {
        if (! $this->hasRelationship() && $this->getUserModel() === null) {
            return parent::getSelectedRecord();
        }

        return $this->queryEngine()->resolveRecordForValue($this->getState());
    }

    public function getCustomUserSelectSelectedRecordResolver(): ?Closure
    {
        return $this->getSelectedRecordUsing === $this->defaultRelationshipRecordResolver
            ? null : $this->getSelectedRecordUsing;
    }

    public function hasClientSideOptionList(): bool
    {
        if ($this->userModel !== null) {
            return false;
        }

        return parent::hasClientSideOptionList();
    }

    public function hasDynamicOptions(): bool
    {
        if ($this->userModel !== null) {
            return true;
        }

        return parent::hasDynamicOptions();
    }

    public function hasInitialNoOptionsMessage(): bool
    {
        if ($this->userModel !== null) {
            return false;
        }

        return parent::hasInitialNoOptionsMessage();
    }

    public function getUserModel(): ?string
    {
        return PickerConfiguration::optionalText($this->evaluate($this->userModel), 'optionModel');
    }

    public function getModifyQueryUsing(): ?Closure
    {
        return $this->modifyQueryUsing;
    }

    /**
     * Preserve the same relationship constraints for search and batched labels.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyUserSelectRelationshipScope(Builder $query, ?string $search = null): Builder
    {
        $record = $this->getRecord();
        if ($this->shouldIgnoreRelationshipRecord && $record instanceof Model) {
            $query->where($record->getQualifiedKeyName(), '!=', $record->getKey());
        }

        if ($this->modifyRelationshipQueryUsing !== null) {
            $modified = $this->evaluate($this->modifyRelationshipQueryUsing, [
                'query' => $query, 'search' => $search,
            ]);
            if ($modified !== null && ! $modified instanceof Builder) {
                throw new InvalidArgumentException('UserSelect relationship query modifier must return an Eloquent builder or null.');
            }
            $query = $modified ?? $query;
        }

        return $query;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getDefaultSuggestions(): array
    {
        if ($this->getUserModel() === null) {
            return [];
        }

        return $this->queryEngine()->searchRecords(null);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function searchRecords(?string $search): array
    {
        return $this->queryEngine()->searchRecords($search);
    }

    protected function configureModelBindingsIfNeeded(): void
    {
        if ($this->modelBindingsConfigured) {
            return;
        }

        if ($this->userModel === null) {
            return;
        }

        $this->modelBindingsConfigured = true;

        $this->searchable();
        $this->searchDebounce(350);

        $this->getSearchResultsUsing(function (UserSelect $component, ?string $search): array {
            return $component->searchRecords($search);
        });

        $this->getOptionLabelUsing(function (UserSelect $component): ?string {
            $state = $component->getState();

            if ($state instanceof BackedEnum) {
                $state = $state->value;
            }

            if (blank($state)) {
                return null;
            }

            $record = $component->queryEngine()->resolveRecordForValue($state);

            if (! $record instanceof Model) {
                return null;
            }

            return $component->renderUserOption(
                $component->recordToOptionArray($record),
                layout: 'trigger',
            );
        });
    }
}
