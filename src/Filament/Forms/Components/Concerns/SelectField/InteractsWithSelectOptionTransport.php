<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
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

    protected bool $collectingRichRelationshipOptions = false;

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

            $value = $record->getKey();
            if ($component->hasRelationship()) {
                $relationship = $component->getRelationship();
                if (! $relationship instanceof Relation) {
                    throw new \LogicException('Rich relationship options require a valid Eloquent relationship.');
                }
                $key = $component->getQualifiedRelatedKeyNameForRelationship($relationship);
                $value = $record->getAttribute(Str::afterLast($key, '.'));
            }
            $label = $component->formatOptionLabelForJs(
                $component->normalizeOption($value, $option),
                compact: $component->renderingSelectedRelationshipOption,
            );

            if ($component->collectingRichRelationshipOptions) {
                $component->richRelationshipOptions[$value] = ['option' => $option, 'label' => $label];
            }

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

        $options = $this->projectRichRelationshipOptions($options);

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

        return $this->withRichRelationshipProjection(fn (): array => parent::getOptions());
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

    /** Preserve rich selected metadata within native multi-value transport. */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getOptionLabelsForJs(): array
    {
        return $this->withRichRelationshipProjection(fn (): array => parent::getOptionLabelsForJs());
    }

    /**
     * Project within the native query operation, before its result is cached.
     * Selected-label rendering cannot overwrite a cached search projection.
     *
     * @param  Closure(): array<mixed>  $callback
     * @return array<mixed>
     */
    protected function withRichRelationshipProjection(Closure $callback): array
    {
        $previous = $this->richRelationshipOptions;
        $collecting = $this->collectingRichRelationshipOptions;
        $this->richRelationshipOptions = [];
        $this->collectingRichRelationshipOptions = true;

        try {
            return $this->projectRichRelationshipOptions($callback());
        } finally {
            $this->richRelationshipOptions = $previous;
            $this->collectingRichRelationshipOptions = $collecting;
        }
    }

    /**
     * @param  array<mixed>  $options
     * @return array<mixed>
     */
    protected function projectRichRelationshipOptions(array $options): array
    {
        if ($this->richRelationshipOptions === []) {
            return $options;
        }

        foreach ($options as $value => $label) {
            $rich = $this->richRelationshipOptions[$value] ?? null;
            if ($rich !== null && $rich['label'] === $label) {
                $options[$value] = $rich['option'];
            }
        }

        return $options;
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
