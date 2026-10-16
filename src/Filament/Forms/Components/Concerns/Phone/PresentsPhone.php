<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\PhoneNumberInsights;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/** @mixin PhoneField */
trait PresentsPhone
{
    public function getDefaultSuffixIcon(): string|BackedEnum|Htmlable
    {
        $icon = config('filament-flex-fields.ui.phone_suffix_icon');

        if (is_string($icon) && filled($icon)) {
            return $icon;
        }

        return FlexIcon::Smartphone;
    }

    public function getSuffixIcon(): string|BackedEnum|Htmlable|null
    {
        if (! $this->hasSuffixIcon()) {
            return null;
        }

        $icon = $this->evaluate($this->suffixIcon);

        return $icon ?? $this->getDefaultSuffixIcon();
    }

    /**
     * Resolved input placeholder: custom value, else libphonenumber example for country/type, else lang default.
     */
    public function getPhonePlaceholder(): string
    {
        $custom = $this->getPlaceholder();

        if (filled($custom)) {
            return (string) $custom;
        }

        $example = $this->getExampleNationalPlaceholder();

        if (filled($example)) {
            return $example;
        }

        return is_string($message = __('filament-flex-fields::default.phone.placeholder'))
            ? $message
            : 'Phone number';
    }

    /**
     * National-formatted example for the current default country and type constraints.
     */
    public function getExampleNationalPlaceholder(): ?string
    {
        try {
            $util = PhoneNumberUtil::getInstance();
            $country = $this->getDefaultCountryCode();
            $allowedTypes = $this->getAllowedTypes();

            $example = match (true) {
                $allowedTypes !== null && count($allowedTypes) === 1 => $util->getExampleNumberForType($country, $allowedTypes[0]),
                $this->isMobileOnly() => $util->getExampleNumberForType($country, PhoneNumberType::MOBILE),
                $this->isFixedLineOnly() => $util->getExampleNumberForType($country, PhoneNumberType::FIXED_LINE),
                default => $util->getExampleNumber($country),
            };

            if ($example === null) {
                return null;
            }

            return PhoneNumberInsights::formatNationalDisplay(
                $example,
                $util,
                $this->storesNationalDigitsOnly(),
            );
        } catch (NumberParseException|InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    public function getWrapperClasses(): array
    {
        return [
            'fff-phone-field',
            'fff-flex-text-input-field',
            'fff-phone-field--'.$this->getSize(),
            'fff-flex-text-input-field--'.$this->getSize(),
            'fff-phone-field--'.$this->getVariant(),
            'fff-flex-text-input-field--'.$this->getVariant(),
            'fff-rounding-'.$this->getRounding(),
        ];
    }
}
