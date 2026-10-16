<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Geocoding;

use Bjanczak\FilamentFlexFields\Concerns\Location\ConfiguresLocationSearch;
use Bjanczak\FilamentFlexFields\Enums\MapboxSearchType;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use Closure;
use InvalidArgumentException;

/**
 * Geocoding provider, country, language and search configuration.
 */
trait ConfiguresGeocodedSearch
{
    use ConfiguresLocationSearch;

    protected string|Closure|null $geocodedAddressMapboxToken = null;

    /**
     * @var list<string>|Closure|null
     */
    protected array|Closure|null $geocodedAddressCountries = null;

    protected bool|Closure $streetAddressesOnly = false;

    protected bool|Closure $requireStreetNumber = false;

    /**
     * @var list<string>|Closure|null
     */
    protected array|Closure|null $geocodedAddressSearchTypes = null;

    protected string|Closure|null $geocodedLanguage = null;

    protected int|Closure $geocodedMinSearchLength = 2;

    protected int|Closure $geocodedSearchDebounce = 350;

    public function mapboxToken(string|Closure|null $token): static
    {
        $this->geocodedAddressMapboxToken = $token;

        return $this;
    }

    /**
     * @param  list<string>|Closure|null  $countries
     */
    public function countries(array|Closure|null $countries): static
    {
        $this->geocodedAddressCountries = $countries;

        return $this;
    }

    public function streetAddressesOnly(bool|Closure $condition = true): static
    {
        $this->streetAddressesOnly = $condition;

        return $this;
    }

    /**
     * @param  list<MapboxSearchType|string>|Closure|null  $types  null searches all Mapbox place types
     */
    public function searchTypes(array|Closure|null $types): static
    {
        $this->geocodedAddressSearchTypes = $types;

        return $this;
    }

    public function language(string|Closure|null $language): static
    {
        $this->geocodedLanguage = $language;

        return $this;
    }

    public function minSearchLength(int|Closure $length): static
    {
        $this->geocodedMinSearchLength = $length;

        return $this;
    }

    public function searchDebounce(int|Closure $milliseconds): static
    {
        $this->geocodedSearchDebounce = $milliseconds;

        return $this;
    }

    /** Only numbered building addresses; apartment verification is provider-specific. */
    public function requireStreetNumber(bool|Closure $condition = true): static
    {
        $this->requireStreetNumber = $condition;

        return $this;
    }

    public function isStreetNumberRequired(): bool
    {
        return (bool) $this->evaluate($this->requireStreetNumber);
    }

    public function isStreetAddressesOnly(): bool
    {
        return $this->isStreetNumberRequired() || (bool) $this->evaluate($this->streetAddressesOnly);
    }

    /**
     * @return list<string>|null
     */
    public function getSearchTypes(): ?array
    {
        if ($this->isStreetAddressesOnly()) {
            return [MapboxSearchType::Address->value];
        }

        return $this->normalizeSearchTypes($this->evaluate($this->geocodedAddressSearchTypes));
    }

    /**
     * @param  list<MapboxSearchType|string>|null  $types
     * @return list<string>|null
     */
    protected function normalizeSearchTypes(mixed $types): ?array
    {
        if ($types === null) {
            return null;
        }

        if (! is_array($types)) {
            throw new InvalidArgumentException('Mapbox search types must be an array or null.');
        }
        if ($types === []) {
            return null; // Compatibility: Mapbox uses an omitted type filter for an empty list.
        }

        $allowed = MapboxSearchType::values();
        $normalized = [];

        foreach ($types as $type) {
            if (! $type instanceof MapboxSearchType && ! is_string($type)) {
                throw new InvalidArgumentException('Mapbox search types must be enum cases or strings.');
            }
            $value = $type instanceof MapboxSearchType ? $type->value : strtolower(trim($type));

            if ($value === '') {
                continue;
            }

            if (! in_array($value, $allowed, true)) {
                throw new InvalidArgumentException("Mapbox search type [{$value}] is not supported.");
            }

            $normalized[] = $value;
        }

        return $normalized === [] ? null : array_values(array_unique($normalized));
    }

    public function getMapboxToken(): ?string
    {
        $token = $this->evaluate($this->geocodedAddressMapboxToken);

        if (is_string($token) && filled($token)) {
            return $token;
        }

        $configured = config('filament-flex-fields.mapbox.access_token');

        return is_string($configured) && filled($configured) ? $configured : null;
    }

    public function usesServerGeocoding(): bool
    {
        return (bool) config('filament-flex-fields.mapbox.use_server_proxy', true);
    }

    public function getGeocodeSearchUrl(): ?string
    {
        if (($endpoint = $this->resolveLocationEndpoint($this->locationSearchEndpoint)) !== null) {
            return $endpoint;
        }
        if (! $this->usesServerGeocoding()) {
            return null;
        }

        return route('filament-flex-fields.geocode.search');
    }

    public function getGeocodeReverseUrl(): ?string
    {
        if (($endpoint = $this->resolveLocationEndpoint($this->locationReverseEndpoint)) !== null) {
            return $endpoint;
        }
        if (! $this->usesServerGeocoding()) {
            return null;
        }

        return route('filament-flex-fields.geocode.reverse');
    }

    public function getLanguage(): string
    {
        $language = $this->evaluate($this->geocodedLanguage);

        if (is_string($language) && $language !== '') {
            return $language;
        }

        $configured = config('filament-flex-fields.mapbox.default_language');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $locale = (string) app()->getLocale();

        return $locale !== '' ? str_replace('_', '-', $locale) : 'en';
    }

    public function getMinSearchLength(): int
    {
        return max(1, min(256, PickerConfiguration::integer($this->evaluate($this->geocodedMinSearchLength), 'minSearchLength')));
    }

    public function getSearchDebounce(): int
    {
        return max(0, min(5000, PickerConfiguration::integer($this->evaluate($this->geocodedSearchDebounce), 'searchDebounce')));
    }

    /**
     * @return list<string>|null
     */
    public function getCountries(): ?array
    {
        $countries = $this->evaluate($this->geocodedAddressCountries);

        if ($countries === null) {
            return null;
        }

        $codes = IdentifierList::normalize($countries, uppercase: true);
        foreach ($codes as $code) {
            if (! preg_match('/^[A-Z]{2}$/D', $code)) {
                throw new InvalidArgumentException('Mapbox country restrictions must use two-letter ISO codes.');
            }
        }

        return $codes;
    }
}
