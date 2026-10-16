<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Support\Location\Coordinates;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureCollection;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureProperties;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration as Config;
use InvalidArgumentException;

/**
 * @mixin MapPickerField
 */
trait ConfiguresLocationMap
{
    /**
     * @var array<string, mixed>
     */
    protected array $locationMapOptions = [];

    use ConfiguresMapCamera;
    use ConfiguresMapInteraction;
    use ConfiguresMapProvider;
    use ConfiguresMapSelection;
    use ConfiguresZoneEditor;

    public function getMapSelectionMode(): string
    {
        return Config::choice($this->evaluate($this->locationMapOptions['selectionMode'] ?? 'point'), ['point', 'markers', 'zones', 'features'], 'selectionMode');
    }

    /**
     * One evaluated, validated snapshot is used for rendering and wire identity.
     *
     * @return array<string, mixed>
     */
    public function getMapConfiguration(): array
    {
        $options = array_map(fn (mixed $value): mixed => $this->evaluate($value), $this->locationMapOptions);
        $provider = Config::choice($options['provider'] ?? config('filament-flex-fields.location.map_provider', 'mapbox'), ['mapbox', 'maplibre'], 'mapProvider');
        $defaults = $provider === 'mapbox' ? ['light' => 'mapbox://styles/mapbox/streets-v12', 'dark' => 'mapbox://styles/mapbox/dark-v11'] : ['light' => 'https://tiles.openfreemap.org/styles/positron', 'dark' => 'https://tiles.openfreemap.org/styles/dark'];
        $styles = $options['styles'] ?? $defaults;
        if (! is_array($styles) || ! isset($styles['light'], $styles['dark'])) {
            throw new InvalidArgumentException('mapStyles requires light and dark styles.');
        }
        foreach ($styles as $key => $style) {
            $styles[$key] = $this->normalizeMapStyle($style);
        }
        $min = $this->mapNumber($options, 'minZoom', 0, 0, 22);
        $max = $this->mapNumber($options, 'maxZoom', 22, 0, 22);
        if ($min > $max) {
            throw new InvalidArgumentException('minZoom cannot exceed maxZoom.');
        }
        $radius = $options['allowedRadius'] ?? null;
        if ($radius !== null) {
            if (! is_array($radius)) {
                throw new InvalidArgumentException('allowedRadius must have a center and meters.');
            }
            $radius = ['center' => Coordinates::pair($radius['center'] ?? null), 'meters' => $this->mapNumber($radius, 'meters', 0, 1, 20000000)];
        }
        $assets = $options['assets'] ?? config('filament-flex-fields.location.'.$provider.'_assets', $provider === 'mapbox' ? ['script' => 'https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.js', 'stylesheet' => 'https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.css', 'module' => false] : ['script' => 'https://unpkg.com/maplibre-gl@6.11.2/dist/maplibre-gl.mjs', 'stylesheet' => 'https://unpkg.com/maplibre-gl@6.11.2/dist/maplibre-gl.css', 'module' => true]);
        if (! is_array($assets)) {
            throw new InvalidArgumentException('mapAssets must be an array.');
        }
        foreach (['script', 'stylesheet'] as $key) {
            $assets[$key] = $this->mapAssetUrl($assets[$key] ?? null);
        }
        $assets['module'] = $assets['module'] ?? $provider === 'maplibre';
        if (! is_bool($assets['module'])) {
            throw new InvalidArgumentException('Map asset module flag must be boolean.');
        }
        $configuration = ['provider' => $provider, 'selectionMode' => Config::choice($options['selectionMode'] ?? 'point', ['point', 'markers', 'zones', 'features'], 'selectionMode'), 'styles' => $styles, 'style' => isset($options['style']) ? $this->normalizeMapStyle($options['style']) : null, 'theme' => Config::choice($options['theme'] ?? 'auto', ['auto', 'light', 'dark'], 'mapTheme'), 'height' => $this->mapInteger($options, 'height', 360, 240, 1200), 'minZoom' => $min, 'maxZoom' => $max, 'pitch' => $this->mapNumber($options, 'pitch', 0, 0, 60), 'bearing' => $this->mapNumber($options, 'bearing', 0, -360, 360), 'bounds' => Coordinates::bounds($options['bounds'] ?? null), 'allowedBounds' => Coordinates::bounds($options['allowedBounds'] ?? null), 'allowedRadius' => $radius, 'geolocationTimeout' => $this->mapInteger($options, 'geolocationTimeout', 10000, 1000, 60000), 'animationDuration' => $this->mapInteger($options, 'animationDuration', 450, 0, 3000), 'minFeatures' => $this->mapInteger($options, 'minFeatures', 0, 0, FeatureCollection::MAX_FEATURES), 'maxFeatures' => $this->mapInteger($options, 'maxFeatures', 100, 1, FeatureCollection::MAX_FEATURES), 'historyLimit' => $this->mapInteger($options, 'historyLimit', 32, 1, 100), 'snapDistance' => $this->mapInteger($options, 'snapDistance', 0, 0, 40), 'maxZoneVertices' => $this->mapInteger($options, 'maxZoneVertices', 128, 3, 511), 'minZoneArea' => $this->mapNumber($options, 'minZoneArea', 0, 0, 500000000000000.0), 'maxZoneArea' => $this->mapNumber($options, 'maxZoneArea', 500000000000000.0, 1, 500000000000000.0), 'drawingTools' => $options['drawingTools'] ?? ['polygon', 'rectangle', 'circle'], 'zoneColors' => $options['zoneColors'] ?? ['#3b82f6', '#22c55e', '#f97316', '#8b5cf6', '#ef4444'], 'pointSize' => $this->mapInteger($options, 'pointSize', 18, 12, 48), 'clusterRadius' => $this->mapInteger($options, 'clusterRadius', 50, 20, 100), 'color' => Config::text($options['color'] ?? '#3b82f6', 'markerColor'), 'scaleUnit' => Config::choice($options['scaleUnit'] ?? 'metric', ['metric', 'imperial', 'nautical'], 'scaleUnit'), 'features' => FeatureCollection::normalize($options['features'] ?? null), 'assets' => $assets, 'workerUrl' => isset($options['workerUrl']) ? $this->mapAssetUrl($options['workerUrl']) : null];
        $configuration['featureListMaxHeight'] = isset($options['featureListMaxHeight'])
            ? $this->mapInteger($options, 'featureListMaxHeight', 320, 128, 4096)
            : null;
        $configuration['extraFeatureProperties'] = FeatureProperties::extraKeys($options['extraFeatureProperties'] ?? []);
        $configuration['clusterMaxZoom'] = $this->mapInteger($options, 'clusterMaxZoom', max(0, min(14, (int) ceil($max) - 1)), 0, 20);
        $configuration['clusterMinimumPoints'] = $this->mapInteger($options, 'clusterMinimumPoints', 2, 2, 100);
        $configuration['quickFeatureEditing'] = $options['quickFeatureEditing'] ?? true;
        if (! is_bool($configuration['quickFeatureEditing'])) {
            throw new InvalidArgumentException('quickFeatureEditing must resolve to boolean.');
        }
        if (! preg_match('/^#[a-fA-F0-9]{6}$/D', $configuration['color'])) {
            throw new InvalidArgumentException('markerColor must use #RRGGBB.');
        }
        if ($configuration['minFeatures'] > $configuration['maxFeatures']) {
            throw new InvalidArgumentException('minFeatures cannot exceed maxFeatures.');
        }
        if ($configuration['minZoneArea'] > $configuration['maxZoneArea']) {
            throw new InvalidArgumentException('Zone minimum area cannot exceed maximum.');
        }
        foreach (['legendTitle', 'legendDescription'] as $key) {
            $text = isset($options[$key]) ? Config::text($options[$key], $key) : null;
            if ($text !== null && strlen($text) > 256) {
                throw new InvalidArgumentException('Map legend text is limited to 256 bytes.');
            }
            $configuration[$key] = $text;
        }
        $configuration['measurementUnit'] = Config::choice($options['measurementUnit'] ?? 'meters', ['meters', 'kilometers', 'feet', 'miles', 'nautical'], 'measurementUnit');
        $configuration['circleSegments'] = $this->mapInteger($options, 'circleSegments', 64, 16, 128);
        $configuration['minCircleRadius'] = $this->mapNumber($options, 'minCircleRadius', .1, .1, 10000000);
        $configuration['maxCircleRadius'] = $this->mapNumber($options, 'maxCircleRadius', 10000000, .1, 10000000);
        if ($configuration['minCircleRadius'] > $configuration['maxCircleRadius']) {
            throw new InvalidArgumentException('Circle minimum radius cannot exceed maximum.');
        }
        $configuration['maxLineVertices'] = $this->mapInteger($options, 'maxLineVertices', 128, 2, 512);
        $configuration['geometryStrokeWidth'] = $this->mapNumber($options, 'geometryStrokeWidth', 2, .5, 12);
        $configuration['zoneFillOpacity'] = $this->mapNumber($options, 'zoneFillOpacity', .18, 0, 1);
        $allowed = match ($configuration['selectionMode']) {
            'markers', 'point' => ['Point'],
            'zones' => ['Polygon'],
            default => ['Point', 'LineString', 'Polygon'],
        };
        $types = $options['allowedGeometryTypes'] ?? $allowed;
        if (! is_array($types) || ! array_is_list($types) || $types === [] || count($types) > 3) {
            throw new InvalidArgumentException('allowedGeometryTypes requires one to three geometry names.');
        }
        foreach ($types as $type) {
            if (! is_string($type) || ! in_array($type, $allowed, true)) {
                throw new InvalidArgumentException('Geometry type is not supported by the selection mode.');
            }
        }
        $configuration['allowedGeometryTypes'] = array_values(array_unique($types));
        $toolTypes = ['point' => 'Point', 'polyline' => 'LineString', 'polygon' => 'Polygon', 'rectangle' => 'Polygon', 'circle' => 'Polygon'];
        if (! isset($options['drawingTools'])) {
            $configuration['drawingTools'] = array_keys(array_filter($toolTypes, fn (string $type): bool => in_array($type, $types, true)));
        }
        foreach (['drawingTools', 'zoneColors'] as $key) {
            if (! is_array($configuration[$key]) || ! array_is_list($configuration[$key]) || count($configuration[$key]) > 12) {
                throw new InvalidArgumentException("Invalid {$key} list.");
            }
            foreach ($configuration[$key] as $value) {
                if (! is_string($value) || ($key === 'drawingTools' ? (! isset($toolTypes[$value]) || ! in_array($toolTypes[$value], $types, true)) : ! preg_match('/^#[a-fA-F0-9]{6}$/D', $value))) {
                    throw new InvalidArgumentException("Invalid {$key} entry.");
                }
            }
            $configuration[$key] = array_values(array_unique($configuration[$key]));
        }
        if (in_array('circle', $configuration['drawingTools'], true) && $configuration['maxZoneVertices'] < 16) {
            throw new InvalidArgumentException('Circle drawing requires at least 16 zone vertices.');
        }
        if ($configuration['zoneColors'] === []) {
            throw new InvalidArgumentException('At least one zone color is required.');
        }
        $cluster = $options['cluster'] ?? false;
        if (! is_bool($cluster)) {
            throw new InvalidArgumentException('Map cluster must resolve to boolean.');
        }
        $configuration['cluster'] = $cluster;

        foreach (['deliveryAppearance' => false, 'preventZoneOverlap' => false, 'allowZoneTouching' => true, 'collisionWithOverlays' => false, 'snapToVertices' => true, 'snapToSegments' => true, 'resizableZones' => true, 'shapeDimensions' => true, 'editableZones' => true, 'deletableFeatures' => true, 'requireZoneLabels' => false, 'draggable' => true, 'selectOnClick' => true, 'reverseGeocoding' => true, 'geolocation' => false, 'highAccuracy' => false, 'manualCoordinates' => false, 'clearable' => true, 'fitControl' => true, 'compactControls' => false, 'zoomControls' => true, 'compass' => true, 'fullscreen' => false, 'scale' => false, 'scrollZoom' => false, 'cooperativeGestures' => true, 'fitFeatures' => false, 'legend' => false, 'featurePopups' => true, 'featureTooltips' => true, 'zoneMetrics' => true, 'lazy' => true] as $key => $default) {
            $value = $options[$key] ?? $default;
            if (! is_bool($value)) {
                throw new InvalidArgumentException("Map {$key} must resolve to boolean.");
            }
            $configuration[$key] = $value;
        }
        if ($configuration['selectionMode'] !== 'point' && $this->getStoreFormat() !== self::STORE_STRUCTURED) {
            throw new InvalidArgumentException('Markers and zones require structured GeoJSON storage.');
        }
        if ($configuration['cluster'] && ($configuration['selectionMode'] !== 'markers' || $configuration['clusterMaxZoom'] >= $max)) {
            throw new InvalidArgumentException('Clustering requires marker mode and clusterMaxZoom below maxZoom.');
        }
        if (in_array($configuration['selectionMode'], ['markers', 'features'], true) && $this->isStreetAddressesOnly()) {
            throw new InvalidArgumentException('Street-address validation applies to point mode; marker mode stores coordinates.');
        }

        $configuration['experience'] = $this->getMapExperienceConfiguration();

        return $configuration;
    }

    /**
     * @return string|array<string, mixed>
     */
    private function normalizeMapStyle(mixed $style): string|array
    {
        if (is_array($style)) {
            if (($style['version'] ?? null) !== 8 || strlen(json_encode($style, JSON_THROW_ON_ERROR)) > 1048576) {
                throw new InvalidArgumentException('Map style must be bounded Style Specification v8.');
            }

            return $style;
        }
        $text = Config::text($style, 'mapStyle');
        if (! str_starts_with($text, 'mapbox://styles/')) {
            return $this->mapAssetUrl($text);
        }

        return $text;
    }

    private function mapAssetUrl(mixed $url): string
    {
        $url = Config::text($url, 'map URL');
        if (! preg_match('~^(?:https://[^\s]+|/(?!/)[^\s]*)$~D', $url)) {
            throw new InvalidArgumentException('Map resources require HTTPS or same-origin absolute paths.');
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function mapNumber(array $options, string $key, float $default, float $min, float $max): float
    {
        $value = Config::number($options[$key] ?? $default, $key);
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Map {$key} is outside its supported range.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function mapInteger(array $options, string $key, int $default, int $min, int $max): int
    {
        $value = Config::integer($options[$key] ?? $default, $key);
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Map {$key} is outside its supported range.");
        }

        return $value;
    }
}
