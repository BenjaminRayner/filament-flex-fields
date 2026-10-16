<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Geocoding;

use Closure;

/** Provider overrides belong to the current request or queue job. */
final class GeocodingState
{
    public ?GeocodingProviderContract $provider = null;

    /** @var (Closure(): GeocodingProviderContract)|null */
    public ?Closure $factory = null;
}
