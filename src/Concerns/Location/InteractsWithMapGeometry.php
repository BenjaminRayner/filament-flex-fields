<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Support\Location\Coordinates;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureCollection;
use Bjanczak\FilamentFlexFields\Support\Location\ZoneCollision;
use Bjanczak\FilamentFlexFields\Support\Translations;

/**
 * @mixin MapPickerField
 */
trait InteractsWithMapGeometry
{
    protected function setUpMapGeometry(): void
    {
        // Registered after the address hooks, so collection modes have their own state boundary.
        $this->afterStateHydrated(function (self $component, mixed $state): void {
            if ($component->getMapSelectionMode() !== 'point') {
                $component->state($component->normalizeMapGeometry($state));
            } else {
                $component->state($component->hydrateToCanonical($state));
            }
        });
        $this->dehydrateStateUsing(fn (self $component, mixed $state): mixed => $component->getMapSelectionMode() === 'point' ? $component->dehydrateFromCanonical($component->hydrateToCanonical($state)) : $component->normalizeMapGeometry($state));
    }

    /**
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function normalizeMapGeometry(mixed $state): array
    {
        $config = $this->getMapConfiguration();

        return FeatureCollection::normalize($state, $config['allowedGeometryTypes'], $config['maxFeatures'], $config['extraFeatureProperties']);
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  array<string, mixed>|null  $configuration
     */
    public function isLocationAllowed(array $point, ?array $configuration = null): bool
    {
        $config = $configuration ?? $this->getMapConfiguration();
        $radius = $config['allowedRadius'];

        return Coordinates::within($point, $config['allowedBounds']) && ($radius === null || Coordinates::distance($radius['center'], $point) <= $radius['meters']);
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    public function getMapValidationMessage(array $canonical, bool $enforceMinimum = true): ?string
    {
        $config = $this->getMapConfiguration();
        if ($config['selectionMode'] === 'point') {
            $lat = $canonical['lat'] ?? null;
            $lng = $canonical['lng'] ?? null;
            if (($lat === null) !== ($lng === null)) {
                return Translations::get('filament-flex-fields::location.validation.coordinate_pair');
            }
            if ($lat !== null && $lng !== null && ! $this->isLocationAllowed(Coordinates::pair([$lng, $lat]), $config)) {
                return Translations::get('filament-flex-fields::location.validation.outside_area');
            }

            return null;
        }
        $collection = $this->normalizeMapGeometry($canonical);
        $count = count($collection['features']);
        if ($enforceMinimum && $count === 0 && $this->isRequired()) {
            return Translations::get('validation.required', ['attribute' => $this->getLabel()]);
        }
        if ($enforceMinimum && $count < $config['minFeatures']) {
            return Translations::get('filament-flex-fields::location.validation.minimum_features', ['count' => $config['minFeatures']]);
        }
        foreach ($collection['features'] as $feature) {
            if ($feature['geometry']['type'] === 'LineString' && count($feature['geometry']['coordinates']) > $config['maxLineVertices']) {
                return Translations::get('filament-flex-fields::location.validation.zone_size');
            }
            if ($feature['geometry']['type'] !== 'Polygon') {
                continue;
            }
            $shape = $feature['properties']['shape'] ?? null;
            if (($shape['kind'] ?? null) === 'circle' && ($shape['radius'] < $config['minCircleRadius'] || $shape['radius'] > $config['maxCircleRadius'])) {
                return Translations::get('filament-flex-fields::location.validation.zone_size');
            }
            $ring = $feature['geometry']['coordinates'][0];
            $area = FeatureCollection::area($ring);
            if (count($ring) - 1 > $config['maxZoneVertices'] || $area < $config['minZoneArea'] || $area > $config['maxZoneArea']) {
                return Translations::get('filament-flex-fields::location.validation.zone_size');
            }
            if ($config['requireZoneLabels'] && trim($feature['properties']['label'] ?? '') === '') {
                return Translations::get('filament-flex-fields::location.validation.zone_label');
            }
        }
        if (ZoneCollision::detected($collection, $config)) {
            return Translations::get('filament-flex-fields::location.validation.zone_overlap');
        }
        foreach (FeatureCollection::positions($collection) as $point) {
            if (! $this->isLocationAllowed($point, $config)) {
                return Translations::get('filament-flex-fields::location.validation.outside_area');
            }
        }

        return null;
    }
}
