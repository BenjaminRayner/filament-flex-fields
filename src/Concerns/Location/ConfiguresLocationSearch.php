<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Support\Location\Coordinates;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration as Config;
use Closure;
use InvalidArgumentException;

trait ConfiguresLocationSearch
{
    protected int|Closure $locationSearchLimit = 6;

    /**
     * @var array{0: float, 1: float, 2: float, 3: float}|Closure|null
     */
    protected array|Closure|null $locationSearchBounds = null;

    /**
     * @var array{0: float, 1: float}|Closure|null
     */
    protected array|Closure|null $locationSearchProximity = null;

    protected string|Closure|null $locationSearchEndpoint = null;

    protected string|Closure|null $locationReverseEndpoint = null;

    protected bool|Closure $locationShowDetails = false;

    public function searchLimit(int|Closure $limit): static
    {
        $this->locationSearchLimit = $limit;

        return $this;
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|Closure|null  $bounds
     */
    public function searchBounds(array|Closure|null $bounds): static
    {
        $this->locationSearchBounds = $bounds;

        return $this;
    }

    /**
     * @param  array{0: float, 1: float}|Closure|null  $lngLat
     */
    public function searchProximity(array|Closure|null $lngLat): static
    {
        $this->locationSearchProximity = $lngLat;

        return $this;
    }

    public function geocodingEndpoints(string|Closure|null $search, string|Closure|null $reverse = null): static
    {
        $this->locationSearchEndpoint = $search;
        $this->locationReverseEndpoint = $reverse;

        return $this;
    }

    public function addressDetails(bool|Closure $enabled = true): static
    {
        $this->locationShowDetails = $enabled;

        return $this;
    }

    public function shouldShowAddressDetails(): bool
    {
        return (bool) $this->evaluate($this->locationShowDetails);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLocationSearchConfiguration(): array
    {
        $limit = Config::integer($this->evaluate($this->locationSearchLimit), 'searchLimit');
        if ($limit < 1 || $limit > 10) {
            throw new InvalidArgumentException('searchLimit must be between 1 and 10.');
        }
        $proximity = $this->evaluate($this->locationSearchProximity);
        $bounds = Coordinates::bounds($this->evaluate($this->locationSearchBounds));
        if ($bounds !== null && $bounds[0] >= $bounds[2]) {
            throw new InvalidArgumentException('Search bounding boxes cannot cross the antimeridian.');
        }

        return ['limit' => $limit, 'bounds' => $bounds, 'proximity' => $proximity === null ? null : Coordinates::pair($proximity)];
    }

    protected function resolveLocationEndpoint(string|Closure|null $configured): ?string
    {
        $url = Config::optionalText($this->evaluate($configured), 'geocoding endpoint');
        // Browser proxy carries session/CSRF information. Never permit a foreign origin.
        if ($url !== null && ! preg_match('~^/(?!/)[^\s]*$~D', $url)) {
            throw new InvalidArgumentException('Custom geocoding endpoints must be same-origin absolute paths.');
        }

        return $url;
    }
}
