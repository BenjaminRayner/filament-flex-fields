<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Location;

final class MapExampleData
{
    /** @return array<string, mixed> */
    public static function zones(): array
    {
        return ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'id' => 'north', 'properties' => ['label' => 'North delivery', 'metric' => '84%', 'color' => '#3b82f6'], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[20.99, 52.25], [21.04, 52.26], [21.055, 52.235], [21.012, 52.23], [20.99, 52.25]]]]],
            ['type' => 'Feature', 'id' => 'central', 'properties' => ['label' => 'Central express', 'metric' => '96%', 'color' => '#22c55e'], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[21.012, 52.23], [21.055, 52.235], [21.06, 52.21], [21.02, 52.20], [21.012, 52.23]]]]],
            ['type' => 'Feature', 'id' => 'south', 'properties' => ['label' => 'South scheduled', 'metric' => '71%', 'color' => '#f97316'], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[21.02, 52.20], [21.06, 52.21], [21.065, 52.185], [21.025, 52.18], [21.02, 52.20]]]]],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function markers(): array
    {
        return ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'id' => 'depot', 'properties' => ['label' => 'Depot', 'description' => 'Warsaw central depot', 'detail' => 'Loading bays · customer pickup', 'value' => 'Open 08:00–18:00', 'color' => '#3b82f6'], 'geometry' => ['type' => 'Point', 'coordinates' => [21.0122, 52.2297]]],
            ['type' => 'Feature', 'id' => 'stop-1', 'properties' => ['label' => 'North stop', 'description' => 'Northern delivery point', 'detail' => 'Same-day deliveries', 'color' => '#22c55e'], 'geometry' => ['type' => 'Point', 'coordinates' => [21.02, 52.245]]],
            ['type' => 'Feature', 'id' => 'stop-2', 'properties' => ['label' => 'South stop', 'description' => 'Southern delivery point', 'detail' => 'Scheduled deliveries', 'color' => '#f97316'], 'geometry' => ['type' => 'Point', 'coordinates' => [21.03, 52.21]]],
        ]];
    }
}
