<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Country;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Support\Countries;
use Bjanczak\FilamentFlexFields\Support\CountryRegistry;
use Bjanczak\FilamentFlexFields\Support\CountryRegistryQueue;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;

/** @mixin CountryField */
trait ResolvesCountryCatalog
{
    public function getBrowserLocaleCountryCode(): ?string
    {
        return Countries::fromBrowserLocale($this->getResolvedCountryCodes());
    }

    /**
     * @return list<string>|null
     */
    public function getAllowedCountryCodes(): ?array
    {
        $countries = $this->evaluate($this->countries);

        if ($countries === null) {
            return null;
        }

        return IdentifierList::normalize($countries, uppercase: true);
    }

    /**
     * @return list<string>
     */
    public function getExceptCountryCodes(): array
    {
        $except = $this->evaluate($this->exceptCountries);

        return IdentifierList::normalize($except, uppercase: true);
    }

    /**
     * @return list<string>
     */
    public function getResolvedCountryCodes(): array
    {
        return Countries::resolve(
            $this->getAllowedCountryCodes(),
            $this->getExceptCountryCodes(),
            $this->getAdditionalRegionCodes(),
        );
    }

    /** @return list<string> */
    public function getAdditionalRegionCodes(): array
    {
        $codes = IdentifierList::normalize($this->evaluate($this->additionalRegions), uppercase: true);

        return array_values(array_intersect(Countries::ADDITIONAL_REGION_CODES, $codes));
    }

    public function getDefaultCountryCode(): ?string
    {
        if ($this->shouldUseBrowserLocaleDefault()) {
            $detected = $this->getBrowserLocaleCountryCode();

            if ($detected !== null) {
                return $detected;
            }
        }

        $default = PickerConfiguration::optionalText($this->evaluate($this->defaultCountry), 'defaultCountry');

        if (blank($default)) {
            return null;
        }

        $country = strtoupper(trim($default));
        $allowed = $this->getResolvedCountryCodes();

        if (in_array($country, $allowed, true)) {
            return $country;
        }

        return $allowed[0] ?? null;
    }

    public function getCountryPool(): string
    {
        return $this->getAdditionalRegionCodes() === [] ? CountryRegistry::POOL_ISO : CountryRegistry::POOL_EXTENDED;
    }

    public function hasCustomCountryCodeFilter(): bool
    {
        return $this->getAllowedCountryCodes() !== null || $this->getExceptCountryCodes() !== [] || $this->getAdditionalRegionCodes() !== [];
    }

    public function getCountryFilterKey(): ?string
    {
        if (! $this->hasCustomCountryCodeFilter()) {
            return null;
        }

        return CountryRegistryQueue::registerCountryFilter($this->getResolvedCountryCodes());
    }

    /**
     * @return array{code: string, name: string, dial_code: string|null, flag_url: string}|null
     */
    public function getSelectedCountryMetadata(): ?array
    {
        $stateValue = $this->getState();
        $selectedCode = is_string($stateValue) && trim($stateValue) !== '' ? strtoupper(trim($stateValue)) : null;

        if ($selectedCode === null) {
            return null;
        }

        $allowed = $this->getResolvedCountryCodes();

        if (! in_array($selectedCode, $allowed, true)) {
            return null;
        }

        $metadata = Countries::metadata([$selectedCode], $this->getExceptCountryCodes(), $this->getLocale(), $this->getAdditionalRegionCodes());

        return $metadata[0] ?? null;
    }

    /**
     * Country metadata for the Alpine dropdown. Built from {@see Countries::metadata()}
     * which memoizes per allowed/except code-set — only the resolved subset is serialized to @js().
     *
     * @return list<array{code: string, name: string, dial_code: string|null, flag_url: string}>
     */
    public function getCountriesMetadata(): array
    {
        $metadata = Countries::metadata(
            $this->getAllowedCountryCodes(),
            $this->getExceptCountryCodes(),
            $this->getLocale(),
            $this->getAdditionalRegionCodes(),
        );

        if ($this->shouldSortCountriesByBrowserLocale()) {
            return Countries::sortWithPreferredFirst(
                $metadata,
                $this->getBrowserLocaleCountryCode(),
            );
        }

        return $metadata;
    }

    /**
     * @return array<string, array{label: string, image: string, description: string}>
     */
    public function getCountrySelectOptions(): array
    {
        return Countries::selectOptions(
            $this->getAllowedCountryCodes(),
            $this->getExceptCountryCodes(),
            $this->getLocale(),
            $this->getAdditionalRegionCodes(),
        );
    }
}
