<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\MapPicker;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;

/** @mixin MapPickerField */
trait PresentsMapPicker
{
    protected function defaultFocusOutline(): bool
    {
        return true;
    }

    /**
     * @return array<string, bool>
     */
    public function getWrapperClasses(): array
    {
        return [
            'fff-map-picker-field' => true,
            'fff-rounding-'.$this->getRounding() => true,
        ];
    }
}
