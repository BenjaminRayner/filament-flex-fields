<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use InvalidArgumentException;

/** Geographic coordinates use GeoJSON order [longitude, latitude]. */
final class Coordinates
{
    /**
     * @return array{0: float, 1: float}
     */
    public static function pair(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) !== 2) {
            throw new InvalidArgumentException('Coordinates must be [longitude, latitude].');
        }
        $lng = PickerConfiguration::number($value[0], 'longitude');
        $lat = PickerConfiguration::number($value[1], 'latitude');
        if (abs($lng) > 180 || abs($lat) > 90) {
            throw new InvalidArgumentException('Coordinates are outside geographic ranges.');
        }

        return [round($lng, 7), round($lat, 7)];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function bounds(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value) || ! array_is_list($value) || count($value) !== 4) {
            throw new InvalidArgumentException('Bounds must be [west, south, east, north].');
        }
        [$west, $south] = self::pair([$value[0], $value[1]]);
        [$east, $north] = self::pair([$value[2], $value[3]]);
        if ($south >= $north || $west === $east) {
            throw new InvalidArgumentException('Bounds must have positive width and height.');
        }

        return [$west, $south, $east, $north];
    }

    /**
     * Bounds crossing the antimeridian have west > east.
     *
     * @param  array{0: float, 1: float}  $point
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bounds
     */
    public static function within(array $point, ?array $bounds): bool
    {
        if ($bounds === null) {
            return true;
        }
        [$lng, $lat] = $point;
        [$west, $south, $east, $north] = $bounds;

        return $lat >= $south && $lat <= $north && ($west < $east ? $lng >= $west && $lng <= $east : $lng >= $west || $lng <= $east);
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    public static function distance(array $a, array $b): float
    {
        $dlat = deg2rad($b[1] - $a[1]);
        $dlng = deg2rad($b[0] - $a[0]);
        $h = sin($dlat / 2) ** 2 + cos(deg2rad($a[1])) * cos(deg2rad($b[1])) * sin($dlng / 2) ** 2;

        return 6371008.8 * 2 * asin(min(1, sqrt($h)));
    }
}
