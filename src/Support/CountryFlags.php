<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl;
use InvalidArgumentException;

/** One local flag resource shared by country and phone pickers. */
final class CountryFlags
{
    /** @var array<string, true>|null */
    private static ?array $knownCodes = null;

    public static function url(string $countryCode): string
    {
        $code = strtoupper($countryCode);
        self::$knownCodes ??= array_fill_keys([...Countries::allCodes(), ...Countries::ADDITIONAL_REGION_CODES], true);
        if (! isset(self::$knownCodes[$code])) {
            throw new InvalidArgumentException('Unsupported country flag code.');
        }

        return PublishedResourceUrl::for('country-flags', 'flags', strtolower($code).'.svg');
    }
}
