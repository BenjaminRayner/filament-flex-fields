<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Livewire\Attributes\Renderless;

/** @mixin SelectField */
trait InteractsWithSelectOptionTransport
{
    protected ?Closure $selectRelationshipQueryUsing = null;

    protected bool $selectIgnoreRelationshipRecord = false;

    protected bool $renderingSelectedRelationshipOption = false;

    /** @var array<string|int, array{option: array<string, mixed>, label: string}> */
    protected array $richRelationshipOptions = [];

    /**
     * Map a relationship record to the same rich option shape accepted by options().
     * Filament retains ownership of relationship queries, validation and persistence.
     *
     * The callback uses Filament utility injection, including the record by name
     * or model type, and returns an array containing at least a label.
     */
    public function getRichOptionFromRecordUsing(Closure $callback): static
    {
        $this->richOptions();

        return $this->getOptionLabelFromRecordUsing(function (SelectField $component, Model $record) use ($callback): string {
            $option = $component->evaluate($callback, ['record' => $record], [
                Model::class => $record,
                $record::class => $record,
            ]);

            if (! is_array($option) || ! array_key_exists('label', $option)) {
                throw new InvalidArgumentException('Rich relationship options must be arrays containing a label.');
            }

            $label = $component->formatOptionLabelForJs(
                $component->normalizeOption($record->getKey(), $option),
                compact: $component->renderingSelectedRelationshipOption,
            );

            $component->richRelationshipOptions[$record->getKey()] = ['option' => $option, 'label' => $label];

            return $label;
        });
    }

    /**
     * The package transport supports rich records as well as Filament strings.
     * Keep the configured transformer authoritative, including custom callbacks.
     *
     * @param  array<string|int, string|array<string, mixed>>  $options
     * @return array<array<string, mixed>>
     */
    protected function transformOptionsForJs(array $options): array
    {
        if ($options === []) {
            return [];
        }

        foreach ($options as $value => $label) {
            $rich = $this->richRelationshipOptions[$value] ?? null;
            if ($rich !== null && $rich['label'] === $label) {
                $options[$value] = $rich['option'];
            }
        }

        $result = $this->evaluate($this->transformOptionsForJsUsing, ['options' => $options]);
        if ($result instanceof Arrayable) {
            $result = $result->toArray();
        }
        if (! is_array($result)) {
            throw new InvalidArgumentException('Select JS transformer must return an array or Arrayable.');
        }

        return $result;
    }

    /**
     * @return array<string | array<string>>
     */
    public function getOptions(): array
    {
        if ($this->shouldDeferHeadlessOptionsUntilOpen() && ! $this->allowDeferredOptionResolution) {
            return [];
        }

        return parent::getOptions();
    }

    /**
     * @return array<array<string, mixed>>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getOptionsForJs(): array
    {
        $previous = $this->allowDeferredOptionResolution;
        $this->allowDeferredOptionResolution = true;

        try {
            return parent::getOptionsForJs();
        } finally {
            $this->allowDeferredOptionResolution = $previous;
        }
    }

    public function hasDynamicSearchResults(): bool
    {
        if ($this->getSearchResultsPageUsing instanceof Closure) {
            return true;
        }

        return parent::hasDynamicSearchResults();
    }

    public function relationship(string|Closure|null $name = null, string|Closure|null $titleAttribute = null, ?Closure $modifyQueryUsing = null, bool $ignoreRecord = false): static
    {
        $this->selectRelationshipQueryUsing = $this->wrapRelationshipQueryModifier($modifyQueryUsing);
        $this->selectIgnoreRelationshipRecord = $ignoreRecord;

        return parent::relationship(
            $name,
            $titleAttribute,
            $this->selectRelationshipQueryUsing,
            $ignoreRecord,
        );
    }
}
