<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\MapPicker;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;

/** @mixin MapPickerField */
trait NormalizesMapPickerState
{
    /**
     * @return list<string>
     */
    protected function geocodedAddressFieldKeys(): array
    {
        return self::ALL_FIELDS;
    }

    protected function geocodedAddressTranslationKey(): string
    {
        return 'map_picker';
    }

    protected function shouldInferPlaceNameFromCoordinates(): bool
    {
        return true;
    }
}
