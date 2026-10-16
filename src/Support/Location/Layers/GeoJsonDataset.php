<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location\Layers;

use Bjanczak\FilamentFlexFields\Support\Location\FeatureCollection;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureMetadata;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureProperties;
use InvalidArgumentException;

/** Overlay ingress is separate from editable geometry: multipart features cannot enter a single-ring editor. */
final class GeoJsonDataset
{
    public const MAX_FEATURES = 10000;

    public const MAX_BYTES = 4194304;

    /** @return array<string, mixed> */
    public static function normalize(mixed $value, string $projection = 'EPSG:4326'): array
    {
        if (! in_array($projection, ['EPSG:4326', 'EPSG:3857'], true)) {
            throw new InvalidArgumentException('Supported input projections: EPSG:4326 and EPSG:3857.');
        }
        if (! is_array($value) || ($value['type'] ?? null) !== 'FeatureCollection'
            || ! is_array($value['features'] ?? null) || ! array_is_list($value['features'])
            || count($value['features']) > self::MAX_FEATURES || FeatureMetadata::bytes($value) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Invalid or oversized map dataset.');
        }
        $features = [];
        $ids = [];
        $vertices = 0;
        foreach ($value['features'] as $index => $feature) {
            if (! is_array($feature) || ($feature['type'] ?? null) !== 'Feature' || ! is_array($feature['geometry'] ?? null)) {
                throw new InvalidArgumentException('Expected a GeoJSON feature.');
            }
            $id = $feature['id'] ?? 'feature-'.$index;
            if ((! is_string($id) && ! is_int($id)) || (string) $id === '' || strlen((string) $id) > 128
                || preg_match('//u', (string) $id) !== 1 || isset($ids[(string) $id])) {
                throw new InvalidArgumentException('Dataset identities must be unique UTF-8 strings.');
            }
            $ids[(string) $id] = true;
            $properties = $feature['properties'] ?? [];
            if (! is_array($properties) || count($properties) > 32) {
                throw new InvalidArgumentException('Expected up to 32 dataset properties.');
            }
            foreach ($properties as $key => $property) {
                if (! is_string($key) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $key)
                    || in_array($key, ['constructor', 'prototype'], true)) {
                    throw new InvalidArgumentException('Invalid dataset property name.');
                }
                FeatureMetadata::normalize($property);
            }
            FeatureProperties::normalize($properties);
            if (FeatureMetadata::bytes($properties) > FeatureMetadata::MAX_PROPERTIES_BYTES) {
                throw new InvalidArgumentException('Dataset properties exceed their byte budget.');
            }
            $geometry = self::geometry($feature['geometry'], $projection, $vertices);
            $features[] = ['type' => 'Feature', 'id' => (string) $id, 'properties' => $properties, 'geometry' => $geometry];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array<string, mixed>
     */
    private static function geometry(array $geometry, string $projection, int &$vertices): array
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;
        $depth = match ($type) {
            'Point' => 0,
            'LineString', 'MultiPoint' => 1,
            'Polygon', 'MultiLineString' => 2,
            'MultiPolygon' => 3,
            default => throw new InvalidArgumentException('Unsupported overlay geometry.'),
        };
        $coordinates = self::coordinates($coordinates, $depth, $projection, $vertices);
        $polygons = $type === 'MultiPolygon' ? $coordinates : ($type === 'Polygon' ? [$coordinates] : []);
        foreach ($polygons as $polygon) {
            if (count($polygon) > 32) {
                throw new InvalidArgumentException('Too many polygon rings.');
            }
            foreach ($polygon as $ring) {
                self::validatePrimitive('Polygon', [$ring]);
            }
            foreach (array_slice($polygon, 1) as $hole) {
                if (! self::inside($hole[0], $polygon[0])) {
                    throw new InvalidArgumentException('Polygon holes must lie inside the exterior ring.');
                }
            }
        }
        if ($type === 'LineString') {
            self::validatePrimitive($type, $coordinates);
        } elseif ($type === 'MultiLineString') {
            foreach ($coordinates as $line) {
                self::validatePrimitive('LineString', $line);
            }
        }

        return ['type' => $type, 'coordinates' => $coordinates];
    }

    /** @return array<mixed> */
    private static function coordinates(mixed $value, int $depth, string $projection, int &$vertices): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw new InvalidArgumentException('Coordinates must be nonempty lists.');
        }
        if ($depth > 0) {
            if (count($value) > 10000) {
                throw new InvalidArgumentException('Coordinate part exceeds its limit.');
            }
            $parts = [];
            foreach ($value as $part) {
                $parts[] = self::coordinates($part, $depth - 1, $projection, $vertices);
            }

            return $parts;
        }
        if (count($value) !== 2 || ++$vertices > 50000) {
            throw new InvalidArgumentException('Expected two-dimensional coordinates within the vertex budget.');
        }
        foreach ($value as $number) {
            if ((! is_float($number) && ! is_int($number)) || ! is_finite((float) $number)) {
                throw new InvalidArgumentException('Expected finite coordinate numbers.');
            }
        }
        if ($projection === 'EPSG:3857') {
            if (abs($value[0]) > 20037508.342789244 || abs($value[1]) > 20037508.342789244) {
                throw new InvalidArgumentException('Web Mercator coordinates exceed the world extent.');
            }
            $value = [rad2deg($value[0] / 6378137), rad2deg(2 * atan(exp($value[1] / 6378137)) - M_PI / 2)];
        }
        if (abs($value[0]) > 180 || abs($value[1]) > 90) {
            throw new InvalidArgumentException('Coordinates exceed WGS84 bounds.');
        }

        return array_map(static fn (int|float $number): float => round((float) $number, 7), $value);
    }

    /** @param array<mixed> $coordinates */
    private static function validatePrimitive(string $type, array $coordinates): void
    {
        FeatureCollection::normalize(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'geometry' => ['type' => $type, 'coordinates' => $coordinates]],
        ]]);
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  list<array{0: float, 1: float}>  $ring
     */
    private static function inside(array $point, array $ring): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            if (($ring[$i][1] > $point[1]) !== ($ring[$j][1] > $point[1])
                && $point[0] < ($ring[$j][0] - $ring[$i][0]) * ($point[1] - $ring[$i][1]) / ($ring[$j][1] - $ring[$i][1]) + $ring[$i][0]) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
