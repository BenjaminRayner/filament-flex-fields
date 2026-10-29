<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionLabelIndex;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionValue;
use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/**
 * @mixin SelectField
 */
trait InteractsWithSelectTriggerPresentation
{
    public function hasDynamicSelectedOptionLabels(): bool
    {
        return $this->getOptionLabelUsing !== null || $this->getOptionLabelsUsing !== null;
    }

    public function getInitialTriggerLabel(): ?string
    {
        if (! $this->shouldUseHeadlessEngine() || $this->getVariant() === 'item-card' || $this->isMultiple()) {
            return null;
        }

        return $this->resolveInitialTriggerLabel();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getInitialTriggerBadges(): array
    {
        if (! $this->shouldUseHeadlessEngine() || $this->getVariant() === 'item-card' || ! $this->isMultiple()) {
            return [];
        }

        $state = $this->resolveStateForItemCardTrigger();

        if (! is_array($state) || $state === []) {
            return [];
        }

        return array_map(static fn (array $entry): array => [
            'value' => $entry['value'], 'label' => $entry['triggerLabel'] ?? $entry['label'],
        ], $this->getHeadlessInitialOptionLabelsForJs());
    }

    protected function resolveInitialTriggerLabel(): string
    {
        $state = OptionValue::normalize($this->resolveStateForItemCardTrigger());

        if (blank($state)) {
            return (string) ($this->getPlaceholder() ?? '');
        }

        if ($this->getOptionLabelUsing && filled($this->getState())) {
            return (string) ($this->getOptionLabel() ?? $state);
        }

        if ($this->hasDynamicOptions() && ! $this->isPreloaded()) {
            return (string) $state;
        }

        $label = $this->findOptionLabel($this->getOptions(), $state);

        if ($label === null) {
            return (string) $state;
        }

        if (is_array($label)) {
            $normalized = $this->normalizeOption($state, $label);

            if ($this->getOptionLayout() === 'grid') {
                return $this->renderRichOptionLabel($normalized, layout: 'trigger');
            }

            if ($this->usesRichOptionHtml()) {
                return $this->formatOptionLabelForJs(
                    $normalized,
                    compact: ! $this->shouldUseRichListTriggerDisplay(),
                );
            }

            return $this->formatOptionLabelForJs($normalized, compact: true);
        }

        return (string) $label;
    }

    public function getItemCardInitialTriggerLabel(): ?string
    {
        if ($this->getVariant() !== 'item-card') {
            return null;
        }

        return $this->resolveInitialTriggerLabel();
    }

    protected function resolveStateForItemCardTrigger(): mixed
    {
        try {
            $state = $this->getState();
        } catch (\Throwable) {
            $state = null;
        }

        if ($state === null || $state === '') {
            return $this->getDefaultState();
        }

        return $state;
    }

    protected function isLocallyDisabled(): bool
    {
        return (bool) $this->evaluate($this->isDisabled);
    }

    protected function hasSelectedValueForClearButton(): bool
    {
        $state = $this->resolveStateForItemCardTrigger();

        if (is_array($state)) {
            return $state !== [];
        }

        return filled($state);
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function getOptionLabel(bool $withDefault = true): ?string
    {
        // Filament's explicit resolver is authoritative, including null used
        // to reject an unavailable selection during server validation.
        if ($this->getOptionLabelUsing) {
            return $this->withSelectedRelationshipOptionRendering(fn (): ?string => parent::getOptionLabel($withDefault));
        }

        $state = OptionValue::normalize($this->getState());
        if ($state === null) {
            return null;
        }

        $label = $this->findOptionLabel($this->getOptions(), $state);

        if ($label === null) {
            return parent::getOptionLabel($withDefault);
        }

        if (is_array($label)) {
            $normalized = $this->normalizeOption($state, $label);

            if ($this->getOptionLayout() === 'grid') {
                return $this->renderRichOptionLabel($normalized, layout: 'trigger');
            }

            return $this->formatOptionLabelForJs($normalized);
        }

        return (string) $label;
    }

    /**
     * @param  array<mixed>|null  $options
     * @return array<string|int, string>
     */
    public function getOptionLabels(bool $withDefaults = true, ?array $options = null): array
    {
        if ($this->getOptionLabelsUsing) {
            // We unconditionally pass $options to support Filament beta signatures.
            // In older versions, PHP silently ignores the extra userland argument.
            /** @phpstan-ignore-next-line */
            return $this->withSelectedRelationshipOptionRendering(fn (): array => parent::getOptionLabels($withDefaults, $options));
        }

        $labels = [];
        $options ??= $this->getOptions();
        $state = $this->getState();
        if (! is_array($state)) {
            return [];
        }
        $index = new OptionLabelIndex($options, fn (array $label): bool => $this->isOptionGroupArray($label));
        foreach ($state as $candidate) {
            $value = OptionValue::normalize($candidate);
            if ($value === null) {
                continue;
            }
            $label = $index->find($value);
            if (is_array($label)) {
                $labels[$value] = $this->formatOptionLabelForJs($this->normalizeOption($value, $label), compact: true);
            } elseif (is_string($label)) {
                $labels[$value] = $label;
            } elseif ($withDefaults) {
                $labels[$value] = (string) $value;
            }
        }

        return $labels;
    }

    /**
     * @param  array<string | int, string | array<string, mixed>>  $options
     * @return array<string, mixed>|string|null
     */
    protected function findOptionLabel(array $options, mixed $state): array|string|null
    {
        return (new OptionLabelIndex($options, fn (array $label): bool => $this->isOptionGroupArray($label)))->find($state);
    }

    protected function withSelectedRelationshipOptionRendering(Closure $callback): mixed
    {
        $previous = $this->renderingSelectedRelationshipOption;
        $this->renderingSelectedRelationshipOption = true;

        try {
            return $callback();
        } finally {
            $this->renderingSelectedRelationshipOption = $previous;
        }
    }
}
