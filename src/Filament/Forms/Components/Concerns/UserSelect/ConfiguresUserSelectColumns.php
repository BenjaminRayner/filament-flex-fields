<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionLabelIndex;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionValue;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @mixin UserSelect
 */
trait ConfiguresUserSelectColumns
{
    protected string|Closure $nameColumn = 'name';

    protected string|Closure|null $emailColumn = null;

    protected string|Closure|null $avatarColumn = null;

    protected string|Closure|null $verificationColumn = null;

    protected ?Closure $getAvatarUrlUsing = null;

    protected ?Closure $getNameUsing = null;

    protected ?Closure $getEmailUsing = null;

    protected ?Closure $isVerifiedUsing = null;

    public function avatarColumn(string|Closure|null $column): static
    {
        $this->avatarColumn = $column;

        return $this;
    }

    public function nameColumn(string|Closure $column): static
    {
        $this->nameColumn = $column;

        return $this;
    }

    public function emailColumn(string|Closure|null $column): static
    {
        $this->emailColumn = $column;

        return $this;
    }

    public function verificationColumn(string|Closure|null $column): static
    {
        $this->verificationColumn = $column;

        return $this;
    }

    public function getAvatarUrlUsing(?Closure $callback): static
    {
        $this->getAvatarUrlUsing = $callback;

        return $this;
    }

    public function getNameUsing(?Closure $callback): static
    {
        $this->getNameUsing = $callback;

        return $this;
    }

    public function getEmailUsing(?Closure $callback): static
    {
        $this->getEmailUsing = $callback;

        return $this;
    }

    public function isVerifiedUsing(?Closure $callback): static
    {
        $this->isVerifiedUsing = $callback;

        return $this;
    }

    public function getNameColumn(): string
    {
        return PickerConfiguration::text($this->evaluate($this->nameColumn), 'nameColumn');
    }

    public function getEmailColumn(): ?string
    {
        $column = PickerConfiguration::optionalText($this->evaluate($this->emailColumn), 'emailColumn');

        return filled($column) ? (string) $column : null;
    }

    public function getAvatarColumn(): ?string
    {
        $column = PickerConfiguration::optionalText($this->evaluate($this->avatarColumn), 'avatarColumn');

        return filled($column) ? (string) $column : null;
    }

    public function getVerificationColumn(): ?string
    {
        $column = PickerConfiguration::optionalText($this->evaluate($this->verificationColumn), 'verificationColumn');

        return filled($column) ? (string) $column : null;
    }

    public function getNameResolver(): ?Closure
    {
        return $this->getNameUsing;
    }

    public function getEmailResolver(): ?Closure
    {
        return $this->getEmailUsing;
    }

    public function getAvatarUrlResolver(): ?Closure
    {
        return $this->getAvatarUrlUsing;
    }

    public function getVerifiedResolver(): ?Closure
    {
        return $this->isVerifiedUsing;
    }

    public function getSelectedRecordResolver(): mixed
    {
        return $this->getSelectedRecordUsing;
    }

    public function getQualifiedRelatedKeyForRelationship(Relation $relationship): string
    {
        return $this->getQualifiedRelatedKeyNameForRelationship($relationship);
    }

    public function findOptionLabelForState(array $options, mixed $state): array|string|null
    {
        return $this->findOptionLabel($options, $state);
    }

    /** One index per selected-value batch, including nested PHP groups.
     * @param  array<int|string, mixed>  $options
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, array<string, mixed>|string>
     */
    public function findOptionLabelsForStates(array $options, array $values): array
    {
        $index = new OptionLabelIndex(
            $options, fn (array $label): bool => $this->isOptionGroupArray($label),
        );
        $labels = [];
        foreach ($values as $value) {
            $key = OptionValue::normalize($value);
            if ($key !== null && ($label = $index->find($key)) !== null) {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }
}
