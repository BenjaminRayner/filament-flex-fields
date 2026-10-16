<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CountryMap;

/** Only immutable package geometry is cached; never locale or configured selection. */
final class CountryMapGeometry
{
    /** @var array<string, string>|null */
    private static ?array $paths = null;

    /** @return array<string, string> */
    public static function paths(): array
    {
        return self::$paths ??= require __DIR__.'/../../Data/country-map-paths.php';
    }
}
