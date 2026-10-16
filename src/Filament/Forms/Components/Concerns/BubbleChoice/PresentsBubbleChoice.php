<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\BubbleChoice;

use Bjanczak\FilamentFlexFields\Support\BubbleChoice\OptionCatalog;
use Bjanczak\FilamentFlexFields\Support\BubbleChoice\SelectionValue;
use Illuminate\Support\Arr;

trait PresentsBubbleChoice
{
    /** @return array<string|int, array{label: string, description: ?string, image: ?string, imageMode: string, color: ?string, selectedColor: ?string, disabled: bool}> */
    public function getNormalizedOptions(): array
    {
        return OptionCatalog::resolve($this->evaluate($this->options), $this->getDisabledOptions());
    }

    /** @return list<array{value: string, label: string, description: ?string, image: ?string, imageMode: string, color: ?string, selectedColor: ?string, disabled: bool}> */
    public function getOptionsForJs(): array
    {
        $result = [];
        foreach ($this->getNormalizedOptions() as $value => $option) {
            $result[] = ['value' => (string) $value, ...$option];
        }

        return $result;
    }

    /** @return array<string|int> */
    public function getDisabledOptions(): array
    {
        return Arr::wrap($this->evaluate($this->disabledOptions));
    }

    /** Presentation helper only. Submitted values are validated without filtering.
     * @param array<int|string, mixed> $state
     * @return list<string> */
    public function normalizeState(array $state): array
    {
        return SelectionValue::preview($state, $this->getNormalizedOptions());
    }

    /**
     * @return list<string>
     */
    public function getWrapperClasses(): array
    {
        return [
            'fff-bubble-choice-field',
            'fff-bubble-choice-field--'.$this->getSize(),
            'fff-rounding-'.$this->getRounding(),
            'fff-bubble-choice-field--'.$this->getVariant(),
            'fff-bubble-choice-field--shape-'.$this->getSelectedShape(),
        ];
    }
}
