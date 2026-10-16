<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use InvalidArgumentException;

/** Semantic shapes supplement standard GeoJSON without replacing its authoritative coordinates. */
final class MapShape
{
    /**
     * @param  array<string, mixed>  $geometry
     * @return array<string, mixed>|null
     */
    public static function normalize(mixed $shape, array $geometry): ?array
    {
        if ($shape === null) {
            return null;
        }
        if (! is_array($shape) || ($geometry['type'] ?? null) !== 'Polygon') {
            throw new InvalidArgumentException('Map shape metadata requires a polygon.');
        }
        if (($shape['kind'] ?? null) === 'circle') {
            $center = Coordinates::pair($shape['center'] ?? null);
            $radius = PickerConfiguration::number($shape['radius'] ?? null, 'circle.radius');
            $segments = PickerConfiguration::integer($shape['segments'] ?? null, 'circle.segments');
            if ($radius < .1 || $radius > 10000000 || $segments < 16 || $segments > 128) {
                throw new InvalidArgumentException('Invalid circle radius or resolution.');
            }
            $safe = ['kind' => 'circle', 'center' => $center, 'radius' => $radius, 'segments' => $segments];
            $ring = [];
            for ($i = 0; $i < $segments; $i++) {
                $ring[] = self::destination($center, $radius, $i * 360 / $segments);
            }
            $ring[] = $ring[0];
        } elseif (($shape['kind'] ?? null) === 'rectangle') {
            $bounds = Coordinates::bounds($shape['bounds'] ?? null);
            if ($bounds === null || $bounds[0] >= $bounds[2] || $bounds[2] - $bounds[0] >= 180) {
                throw new InvalidArgumentException('Rectangles require non-wrapping bounds.');
            }
            $safe = ['kind' => 'rectangle', 'bounds' => $bounds];
            [$w, $s, $e, $n] = $bounds;
            $ring = [[$w, $s], [$e, $s], [$e, $n], [$w, $n], [$w, $s]];
        } else {
            throw new InvalidArgumentException('Unsupported map shape.');
        }
        $actual = $geometry['coordinates'][0];
        if (count($actual) !== count($ring)) {
            throw new InvalidArgumentException('Shape metadata disagrees with geometry.');
        }
        foreach ($ring as $index => $point) {
            if (abs($point[0] - $actual[$index][0]) > 1.0e-7 || abs($point[1] - $actual[$index][1]) > 1.0e-7) {
                throw new InvalidArgumentException('Shape metadata disagrees with geometry.');
            }
        }

        return $safe;
    }

    /**
     * @param  array{0: float, 1: float}  $center
     * @return array{0: float, 1: float}
     */
    private static function destination(array $center, float $meters, float $degrees): array
    {
        $angle = $meters / 6371008.8;
        $bearing = deg2rad($degrees);
        $lat = deg2rad($center[1]);
        $lng = deg2rad($center[0]);
        $latitude = asin(sin($lat) * cos($angle) + cos($lat) * sin($angle) * cos($bearing));
        $longitude = $lng + atan2(sin($bearing) * sin($angle) * cos($lat), cos($angle) - sin($lat) * sin($latitude));
        $wrapped = fmod(fmod(rad2deg($longitude) + 180, 360) + 360, 360) - 180;

        return [round($wrapped, 7), round(rad2deg($latitude), 7)];
    }
}
