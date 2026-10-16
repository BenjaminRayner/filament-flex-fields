<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\IconPicker;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;

/** @mixin IconPickerField */
trait PresentsIconPicker
{
    /**
     * @return array<string, bool|string>
     */
    public function getWrapperClasses(): array
    {
        $classes = [
            'fff-select-field' => true,
            'fff-select-field--'.$this->getSize() => true,
            'fff-select-field--'.$this->getVariant() => true,
            'fi-color-primary' => true,
            'fff-icon-picker-field' => true,
            'fff-icon-picker-field--layout-'.$this->getSearchResultsLayout() => true,
        ];

        if (! $this->isClearable()) {
            $classes['fff-select-field--not-clearable'] = true;
        } elseif ($this->hasSelectedValueForClearButton() && ! $this->isLocallyDisabled()) {
            $classes['fff-select-field--clearable-has-value'] = true;
        }

        if ($this->shouldShowFocusOutline()) {
            $classes['has-focus-outline'] = true;
        }

        return $classes;
    }

    protected function isLocallyDisabled(): bool
    {
        return (bool) $this->evaluate($this->isDisabled)
            || (bool) $this->evaluate($this->isReadOnly);
    }

    protected function hasSelectedValueForClearButton(): bool
    {
        try {
            $state = $this->getState();
        } catch (\Throwable) {
            $state = null;
        }

        if ($state === null || ($state === '' && ! is_array($state))) {
            $state = $this->getDefaultState();
        }

        return filled($state);
    }
}
