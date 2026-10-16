<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Country;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;

/** @mixin CountryField */
trait ConfiguresCountry
{
    protected string|Closure|null $defaultCountry = null;

    /**
     * @var list<string>|Closure|null
     */
    protected array|Closure|null $countries = null;

    /**
     * @var list<string>|Closure
     */
    protected array|Closure $exceptCountries = [];

    /** @var list<string>|Closure */
    protected array|Closure $additionalRegions = [];

    /** @param list<string>|Closure $regions Known non-ISO regions to add, e.g. XK. */
    public function additionalRegions(array|Closure $regions): static
    {
        $this->additionalRegions = $regions;

        return $this;
    }

    protected bool|Closure $showCountryCode = false;

    protected bool|Closure $showDialCode = false;

    protected bool|Closure $browserLocaleDefault = false;

    protected bool|Closure $browserLocaleSortFirst = false;

    protected string|Closure|null $locale = null;

    /**
     * @param  list<string>|Closure|null  $countries
     */
    public function countries(array|Closure|null $countries): static
    {
        $this->countries = $countries;

        return $this;
    }

    /**
     * @param  list<string>|Closure  $countries
     */
    public function exceptCountries(array|Closure $countries): static
    {
        $this->exceptCountries = $countries;

        return $this;
    }

    public function defaultCountry(string|Closure|null $countryCode): static
    {
        $this->defaultCountry = $countryCode;

        return $this;
    }

    public function browserLocaleDefault(bool|Closure $condition = true): static
    {
        $this->browserLocaleDefault = $condition;

        return $this;
    }

    public function browserLocaleSortFirst(bool|Closure $condition = true): static
    {
        $this->browserLocaleSortFirst = $condition;

        return $this;
    }

    public function locale(string|Closure|null $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        $locale = PickerConfiguration::optionalText($this->evaluate($this->locale), 'locale');

        return filled($locale) ? (string) $locale : app()->getLocale();
    }

    public function showCountryCode(bool|Closure $condition = true): static
    {
        $this->showCountryCode = $condition;

        return $this;
    }

    public function showDialCode(bool|Closure $condition = true): static
    {
        $this->showDialCode = $condition;

        return $this;
    }

    public function shouldUseBrowserLocaleDefault(): bool
    {
        return (bool) $this->evaluate($this->browserLocaleDefault);
    }

    public function shouldSortCountriesByBrowserLocale(): bool
    {
        return (bool) $this->evaluate($this->browserLocaleSortFirst);
    }

    public function shouldShowCountryCode(): bool
    {
        return (bool) $this->evaluate($this->showCountryCode);
    }

    public function shouldShowDialCode(): bool
    {
        return (bool) $this->evaluate($this->showDialCode);
    }
}
