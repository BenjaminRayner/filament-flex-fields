<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Support\CountryRegistry;
use Bjanczak\FilamentFlexFields\Support\CountryRegistryQueue;
use Bjanczak\FilamentFlexFields\Support\PhoneCountries;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;

/** @mixin PhoneField */
trait ResolvesPhoneCatalog
{
    public function getBrowserLocaleCountryCode(): ?string
    {
        return PhoneCountries::fromBrowserLocale($this->getResolvedCountryCodes());
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

    public function getDefaultCountryCode(): string
    {
        if ($this->shouldUseBrowserLocaleDefault()) {
            $detected = $this->getBrowserLocaleCountryCode();

            if ($detected !== null) {
                return $detected;
            }
        }

        $country = strtoupper(trim(PickerConfiguration::text($this->evaluate($this->defaultCountry), 'defaultCountry')));
        $allowed = PhoneCountries::resolve($this->getAllowedCountryCodes(), $this->getExceptCountryCodes());

        if (in_array($country, $allowed, true)) {
            return $country;
        }

        return $allowed[0] ?? 'US';
    }

    public function getCountryPool(): string
    {
        return CountryRegistry::POOL_PHONE;
    }

    public function hasCustomCountryCodeFilter(): bool
    {
        return $this->getAllowedCountryCodes() !== null || $this->getExceptCountryCodes() !== [];
    }

    public function getCountryFilterKey(): ?string
    {
        if (! $this->hasCustomCountryCodeFilter()) {
            return null;
        }

        return CountryRegistryQueue::registerCountryFilter($this->getResolvedCountryCodes());
    }

    /**
     * @return list<string>
     */
    public function getResolvedCountryCodes(): array
    {
        return PhoneCountries::resolve(
            $this->getAllowedCountryCodes(),
            $this->getExceptCountryCodes(),
        );
    }

    /**
     * @return array{code: string, name: string, dial_code: string, flag_url: string}|null
     */
    public function getSelectedCountryMetadata(): ?array
    {
        $stateValue = $this->getState();
        $selectedCode = is_array($stateValue) && is_string($stateValue['country'] ?? null) && trim($stateValue['country']) !== ''
            ? strtoupper((string) $stateValue['country'])
            : $this->getDefaultCountryCode();

        $allowed = $this->getResolvedCountryCodes();

        if (! in_array($selectedCode, $allowed, true)) {
            return null;
        }

        $metadata = PhoneCountries::metadata([$selectedCode], $this->getExceptCountryCodes(), $this->getLocale());

        return $metadata[0] ?? null;
    }

    /**
     * Country metadata for the Alpine dropdown. Built from {@see PhoneCountries::metadata()}
     * which memoizes per allowed/except code-set — only the resolved subset is serialized to @js().
     *
     * @return list<array{code: string, name: string, dial_code: string, flag_url: string}>
     */
    public function getCountriesMetadata(): array
    {
        $metadata = PhoneCountries::metadata(
            $this->getAllowedCountryCodes(),
            $this->getExceptCountryCodes(),
            $this->getLocale(),
        );

        if ($this->shouldSortCountriesByBrowserLocale()) {
            return PhoneCountries::sortWithPreferredFirst(
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
        $options = [];
        foreach ($this->getCountriesMetadata() as $country) {
            $options[$country['code']] = [
                'label' => $country['name'],
                'image' => $country['flag_url'],
                'description' => $country['dial_code'],
            ];
        }

        return $options;
    }
}
