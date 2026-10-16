<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Phone appearance, country and browser-locale configuration.
 *
 * @mixin PhoneField
 */
trait ConfiguresPhone
{
    use ConfiguresPhoneNumberPolicy;

    protected string|Closure $variant = 'primary';

    protected string|Closure $defaultCountry = 'PL';

    /**
     * @var list<string>|Closure|null
     */
    protected array|Closure|null $countries = null;

    /**
     * @var list<string>|Closure
     */
    protected array|Closure $exceptCountries = [];

    protected bool|Closure $searchable = true;

    protected string|BackedEnum|Htmlable|Closure|null $suffixIcon = null;

    protected bool|Closure $showSuffixIcon = true;

    protected bool|Closure $showInternationalPrefix = true;

    protected bool|Closure $browserLocaleDefault = false;

    protected bool|Closure $browserLocaleSortFirst = false;

    protected string|Closure|null $locale = null;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->variant), ['primary', 'secondary', 'flat', 'soft'], 'variant');
    }

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

    public function defaultCountry(string|Closure $countryCode): static
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

    public function shouldUseBrowserLocaleDefault(): bool
    {
        return (bool) $this->evaluate($this->browserLocaleDefault);
    }

    public function shouldSortCountriesByBrowserLocale(): bool
    {
        return (bool) $this->evaluate($this->browserLocaleSortFirst);
    }

    public function searchable(bool|Closure $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    public function suffixIcon(string|BackedEnum|Htmlable|Closure|bool|null $icon = null): static
    {
        if (is_bool($icon)) {
            $this->showSuffixIcon = $icon;

            return $this;
        }

        if ($icon !== null) {
            $this->suffixIcon = $icon;
            $this->showSuffixIcon = true;
        }

        return $this;
    }

    public function internationalPrefix(bool|Closure $condition = true): static
    {
        $this->showInternationalPrefix = $condition;

        return $this;
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->searchable);
    }

    public function hasSuffixIcon(): bool
    {
        return (bool) $this->evaluate($this->showSuffixIcon);
    }

    public function showsInternationalPrefix(): bool
    {
        return (bool) $this->evaluate($this->showInternationalPrefix);
    }
}
