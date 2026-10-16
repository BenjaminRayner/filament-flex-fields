<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\CountryMap;

use Bjanczak\FilamentFlexFields\Support\CountryMap\CountryMapCamera;
use Bjanczak\FilamentFlexFields\Support\CountryMap\CountryMapCatalog;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use InvalidArgumentException;

trait ConfiguresCountryMap
{
    /** @var list<string>|Closure|null */
    protected array|Closure|null $mapCountryCodes = null;

    /** @var array<string, list<string>>|Closure */
    protected array|Closure $mapCountryGroups = [];

    protected int|Closure $countryMapHeight = 320;

    protected string|Closure $countryMapColor = '#22c55e';

    protected int|Closure $countryMapMinZoom = 1;

    protected int|Closure $countryMapMaxZoom = 6;

    protected int|Closure $countryMapAnimationDuration = 180;

    protected bool|Closure $countryMapScrollZoom = false;

    protected bool|Closure $countryMapTooltips = true;

    /** @param list<string>|Closure|null $codes */
    public function countries(array|Closure|null $codes): static
    {
        $this->mapCountryCodes = $codes;

        return $this;
    }

    /** @param array<string, list<string>>|Closure $groups */
    public function countryGroups(array|Closure $groups): static
    {
        $this->mapCountryGroups = $groups;

        return $this;
    }

    public function mapZoomRange(int|Closure $min, int|Closure $max): static
    {
        $this->countryMapMinZoom = $min;
        $this->countryMapMaxZoom = $max;

        return $this;
    }

    public function mapAnimationDuration(int|Closure $milliseconds): static
    {
        $this->countryMapAnimationDuration = $milliseconds;

        return $this;
    }

    public function mapScrollZoom(bool|Closure $enabled = true): static
    {
        $this->countryMapScrollZoom = $enabled;

        return $this;
    }

    public function mapTooltips(bool|Closure $enabled = true): static
    {
        $this->countryMapTooltips = $enabled;

        return $this;
    }

    public function mapHeight(int|Closure $pixels): static
    {
        $this->countryMapHeight = $pixels;

        return $this;
    }

    public function mapColor(string|Closure $color): static
    {
        $this->countryMapColor = $color;

        return $this;
    }

    public function getCountryMapCatalog(): CountryMapCatalog
    {
        return CountryMapCatalog::resolve($this->evaluate($this->mapCountryCodes), $this->evaluate($this->mapCountryGroups));
    }

    /** @return list<string> */
    public function getMapCountryCodes(): array
    {
        return $this->getCountryMapCatalog()->codes;
    }

    /** @return array<string, list<string>> */
    public function getCountryGroups(): array
    {
        return $this->getCountryMapCatalog()->groups;
    }

    /** @return array<string, int|bool> */
    public function getCountryMapCamera(): array
    {
        return CountryMapCamera::resolve($this->evaluate($this->countryMapMinZoom), $this->evaluate($this->countryMapMaxZoom), $this->evaluate($this->countryMapAnimationDuration), $this->evaluate($this->countryMapScrollZoom), $this->evaluate($this->countryMapTooltips))->toArray();
    }

    public function getCountryMapHeight(): int
    {
        $height = PickerConfiguration::integer($this->evaluate($this->countryMapHeight), 'country map height');
        if ($height < 200 || $height > 800) {
            throw new InvalidArgumentException('Country map height must be 200–800 pixels.');
        }

        return $height;
    }

    public function getCountryMapColor(): string
    {
        $color = PickerConfiguration::text($this->evaluate($this->countryMapColor), 'country map color');
        if (! preg_match('/^#[a-fA-F0-9]{6}$/D', $color)) {
            throw new InvalidArgumentException('Country map color must use #RRGGBB.');
        }

        return $color;
    }
}
