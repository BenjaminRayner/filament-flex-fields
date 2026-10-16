<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AddressAutocomplete;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Illuminate\Contracts\Support\Htmlable;

/** @mixin AddressAutocompleteField */
trait PresentsAddressAutocomplete
{
    public function getDefaultPrefixIcon(): string|BackedEnum|Htmlable
    {
        $icon = config('filament-flex-fields.ui.address_autocomplete_prefix_icon');

        if (is_string($icon) && filled($icon)) {
            return $icon;
        }

        return FlexIcon::MapPin;
    }

    public function getPrefixIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->prefixIcon);

        return $icon ?? $this->getDefaultPrefixIcon();
    }

    public function getDefaultClearIcon(): string|BackedEnum|Htmlable
    {
        $icon = config('filament-flex-fields.ui.address_autocomplete_clear_icon');

        if (is_string($icon) && filled($icon)) {
            return $icon;
        }

        return FlexIcon::CircleXmark;
    }

    public function getClearIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->clearIcon);

        return $icon ?? $this->getDefaultClearIcon();
    }

    /**
     * @return array<string, bool>
     */
    public function getWrapperClasses(): array
    {
        return [
            'fff-address-autocomplete-field',
            'fff-flex-text-input-field',
            'fff-address-autocomplete-field--'.$this->getSize(),
            'fff-flex-text-input-field--'.$this->getSize(),
            'fff-address-autocomplete-field--'.$this->getVariant(),
            'fff-flex-text-input-field--'.$this->getVariant(),
            'fff-rounding-'.$this->getRounding(),
        ];
    }
}
