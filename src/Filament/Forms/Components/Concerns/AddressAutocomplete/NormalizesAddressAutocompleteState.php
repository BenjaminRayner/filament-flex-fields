<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AddressAutocomplete;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;

/** @mixin AddressAutocompleteField */
trait NormalizesAddressAutocompleteState
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
        return 'address_autocomplete';
    }

    protected function shouldInferPlaceNameFromCoordinates(): bool
    {
        return false;
    }
}
