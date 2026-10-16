<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

use InvalidArgumentException;

/** Bounded GeoJSON ingress shared by submitted geometry and configured overlays. No executable HTML. */
final class FeatureCollection
{
    public const MAX_FEATURES = 200;

    public const MAX_VERTICES = 10000;

    /**
     * @param  list<string>  $types
     * @param  list<string>  $extraPropertyKeys
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public static function normalize(mixed $value, array $types = ['Point', 'LineString', 'Polygon'], int $limit = self::MAX_FEATURES, array $extraPropertyKeys = []): array
    {
        if ($value === null || $value === []) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        if (! is_array($value) || ($value['type'] ?? null) !== 'FeatureCollection' || ! is_array($value['features'] ?? null) || ! array_is_list($value['features'])) {
            throw new InvalidArgumentException('Expected a GeoJSON FeatureCollection.');
        }
        if (count($value['features']) > min(self::MAX_FEATURES, $limit)) {
            throw new InvalidArgumentException('Too many map features.');
        }
        $features = [];
        $ids = [];
        $vertices = 0;
        foreach ($value['features'] as $index => $feature) {
            if (! is_array($feature) || ($feature['type'] ?? null) !== 'Feature' || ! is_array($feature['geometry'] ?? null)) {
                throw new InvalidArgumentException('Invalid GeoJSON feature.');
            }
            $geometry = $feature['geometry'];
            $type = $geometry['type'] ?? null;
            if (! is_string($type) || ! in_array($type, $types, true)) {
                throw new InvalidArgumentException('Unsupported map geometry.');
            }
            $raw = $geometry['coordinates'] ?? null;
            switch ($type) {
                case 'Point':
                    $coordinates = Coordinates::pair($raw);
                    $vertices++;
                    break;
                case 'LineString':
                    $coordinates = self::line($raw, false);
                    $vertices += count($coordinates);
                    break;
                case 'Polygon':
                    $coordinates = self::polygon($raw);
                    $vertices += count($coordinates[0]);
                    break;
                default:
                    throw new InvalidArgumentException('Unsupported map geometry.');
            }
            if ($vertices > self::MAX_VERTICES) {
                throw new InvalidArgumentException('Too many map vertices.');
            }
            $id = $feature['id'] ?? 'feature-'.$index;
            if (! is_string($id) && ! is_int($id)) {
                throw new InvalidArgumentException('Feature identity must be text or integer.');
            }
            $id = (string) $id;
            if ($id === '' || strlen($id) > 128 || ! mb_check_encoding($id, 'UTF-8') || isset($ids[$id])) {
                throw new InvalidArgumentException('Invalid or duplicate feature identity.');
            }
            $ids[$id] = true;
            $properties = $feature['properties'] ?? [];
            $safe = FeatureProperties::normalize($properties, $extraPropertyKeys);
            $shape = MapShape::normalize($properties['shape'] ?? null, ['type' => $type, 'coordinates' => $coordinates]);
            if ($shape !== null) {
                $safe['shape'] = $shape;
            }
            $features[] = ['type' => 'Feature', 'id' => $id, 'geometry' => ['type' => $type, 'coordinates' => $coordinates], 'properties' => $safe];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private static function line(mixed $value, bool $ring): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) < ($ring ? 4 : 2) || count($value) > ($ring ? 512 : self::MAX_VERTICES)) {
            throw new InvalidArgumentException('Invalid line or polygon vertex count.');
        }
        $points = array_map(Coordinates::pair(...), $value);
        if (! $ring) {
            for ($i = 0; $i < count($points) - 1; $i++) {
                if ($points[$i] === $points[$i + 1]) {
                    throw new InvalidArgumentException('Line vertices must be distinct.');
                }
            }

            return $points;
        }
        if ($points[0] !== $points[count($points) - 1]) {
            throw new InvalidArgumentException('Polygon rings must be closed.');
        }
        $area = 0.0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            if ($points[$i] === $points[$i + 1] || abs($points[$i][0] - $points[$i + 1][0]) > 180) {
                throw new InvalidArgumentException('Polygon edges must be distinct and must not cross the antimeridian.');
            }
            $area += $points[$i][0] * $points[$i + 1][1] - $points[$i + 1][0] * $points[$i][1];
            for ($j = $i + 2; $j < count($points) - 1; $j++) {
                if ($i === 0 && $j === count($points) - 2) {
                    continue;
                }
                if (self::intersects($points[$i], $points[$i + 1], $points[$j], $points[$j + 1])) {
                    throw new InvalidArgumentException('Polygon ring must not self-intersect.');
                }
            }
        }
        if (abs($area) < 1.0E-12) {
            throw new InvalidArgumentException('Polygon area must be positive.');
        }

        return $points;
    }

    /**
     * Editing currently supports single outer rings, making topology and ownership explicit.
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    private static function polygon(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) !== 1) {
            throw new InvalidArgumentException('Zones must have one outer polygon ring.');
        }

        return [self::line($value[0], true)];
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @param  array{0: float, 1: float}  $c
     * @param  array{0: float, 1: float}  $d
     */
    private static function intersects(array $a, array $b, array $c, array $d): bool
    {
        $cross = static fn (array $p, array $q, array $r): float => ($q[0] - $p[0]) * ($r[1] - $p[1]) - ($q[1] - $p[1]) * ($r[0] - $p[0]);
        $on = static fn (array $p, array $q, array $r): bool => $r[0] >= min($p[0], $q[0]) && $r[0] <= max($p[0], $q[0]) && $r[1] >= min($p[1], $q[1]) && $r[1] <= max($p[1], $q[1]);
        $abC = $cross($a, $b, $c);
        $abD = $cross($a, $b, $d);
        $cdA = $cross($c, $d, $a);
        $cdB = $cross($c, $d, $b);

        return $abC * $abD < 0 && $cdA * $cdB < 0 || abs($abC) < 1.0E-12 && $on($a, $b, $c) || abs($abD) < 1.0E-12 && $on($a, $b, $d) || abs($cdA) < 1.0E-12 && $on($c, $d, $a) || abs($cdB) < 1.0E-12 && $on($c, $d, $b);
    }

    /**
     * Spherical ring area in square meters.
     *
     * @param  list<array{0: float, 1: float}>  $ring
     */
    public static function area(array $ring): float
    {
        $sum = 0.0;
        for ($i = 0; $i < count($ring) - 1; $i++) {
            $sum += deg2rad($ring[$i + 1][0] - $ring[$i][0]) * (2 + sin(deg2rad($ring[$i][1])) + sin(deg2rad($ring[$i + 1][1])));
        }

        return abs($sum) * 6371008.8 ** 2 / 2;
    }

    /**
     * @param  array<string, mixed>  $collection
     * @return list<array{0: float, 1: float}>
     */
    public static function positions(array $collection): array
    {
        $positions = [];
        foreach ($collection['features'] as $feature) {
            $geometry = $feature['geometry'];
            $points = match ($geometry['type']) {
                'Point' => [$geometry['coordinates']],
                'Polygon' => $geometry['coordinates'][0],
                default => $geometry['coordinates'],
            };
            array_push($positions, ...$points);
        }

        return $positions;
    }
}
