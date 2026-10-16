<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns;

use Bjanczak\FilamentFlexFields\Concerns\Geocoding\ConfiguresGeocodedSearch;
use Bjanczak\FilamentFlexFields\Concerns\Geocoding\ConfiguresGeocodedStorage;
use Bjanczak\FilamentFlexFields\Concerns\Geocoding\NormalizesGeocodedState;
use Bjanczak\FilamentFlexFields\Concerns\Geocoding\ValidatesGeocodedState;

/** Compatibility composition for the existing field API. */
trait InteractsWithGeocodedAddress
{
    use ConfiguresGeocodedSearch;
    use ConfiguresGeocodedStorage;
    use NormalizesGeocodedState;
    use ValidatesGeocodedState;

    public const string STORE_STRUCTURED = 'structured';

    public const string STORE_STRING = 'string';

    /**
     * @return list<string>
     */
    abstract protected function geocodedAddressFieldKeys(): array;

    abstract protected function geocodedAddressTranslationKey(): string;

    abstract protected function shouldInferPlaceNameFromCoordinates(): bool;
}
