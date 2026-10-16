<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location\Layers;

use InvalidArgumentException;

/** A bounded, portable layer policy. SDK expressions stay inside the renderer. */
final class MapLayer
{
    /** @var array<string, mixed> */
    private array $options = ['kind' => 'geometry', 'data' => ['type' => 'FeatureCollection', 'features' => []], 'color' => '#3b82f6', 'radius' => 8, 'width' => 3, 'opacity' => .8, 'cluster' => false, 'clusterRadius' => 50, 'clusterMaxZoom' => 14, 'labelProperty' => null, 'interactive' => true, 'visible' => true, 'heatmapRadius' => 28, 'heatmapIntensity' => 1, 'weightProperty' => 'weight', 'weightMax' => 1, 'colors' => ['#3b82f6', '#22c55e', '#facc15', '#f97316', '#ef4444']];

    private function __construct(private readonly string $id)
    {
        if (! preg_match('/^[a-z][a-z0-9-]{0,47}$/D', $id)) {
            throw new InvalidArgumentException('Layer IDs require a stable lowercase identifier.');
        }
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    /** @param array<string, mixed> $geojson */
    public function data(array $geojson, string $projection = 'EPSG:4326'): self
    {
        $this->options['data'] = GeoJsonDataset::normalize($geojson, $projection);

        return $this;
    }

    public function heatmap(string $weightProperty = 'weight', float $maximum = 1): self
    {
        $this->options['kind'] = 'heatmap';
        $this->options['weightProperty'] = $weightProperty;
        $this->options['weightMax'] = $maximum;

        return $this;
    }

    public function priceMarkers(string $property = 'value'): self
    {
        $this->options['kind'] = 'pills';
        $this->options['labelProperty'] = $property;

        return $this;
    }

    public function trackedMarkers(string $variant = 'dot'): self
    {
        if (! in_array($variant, ['dot', 'car', 'scooter', 'origin', 'destination', 'stop'], true)) {
            throw new InvalidArgumentException('Unknown tracked marker variant.');
        }
        $this->options['kind'] = 'tracked';
        $this->options['markerVariant'] = $variant;

        return $this;
    }

    /** Use an application-owned vehicle image, retaining the shared loader and marker lifecycle. */
    public function markerImage(string $url): self
    {
        $local = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $https = str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false;
        if ((! $local && ! $https) || strlen($url) > 2048 || preg_match('/[\\\\\x00-\x20\x7F]/', $url)) {
            throw new InvalidArgumentException('Vehicle images require a local path or HTTPS URL.');
        }
        $this->options['markerImage'] = $url;

        return $this;
    }

    public function labels(string $property = 'label'): self
    {
        $this->options['labelProperty'] = $property;

        return $this;
    }

    public function color(string $color): self
    {
        $this->options['color'] = $color;

        return $this;
    }

    public function pointRadius(float $pixels): self
    {
        $this->options['radius'] = $pixels;

        return $this;
    }

    /** Screen-pixel navigation route, preserving full street geometry including roundabouts. */
    public function navigationRoute(): self
    {
        $this->options = [...$this->options, 'kind' => 'geometry', 'navigationRoute' => true, 'color' => '#000000', 'width' => 4, 'opacity' => 1, 'interactive' => false];

        return $this;
    }

    public function lineWidth(float $pixels): self
    {
        $this->options['width'] = $pixels;

        return $this;
    }

    public function opacity(float $opacity): self
    {
        $this->options['opacity'] = $opacity;

        return $this;
    }

    public function cluster(bool $enabled = true, int $radius = 50, int $maxZoom = 14): self
    {
        $this->options['cluster'] = $enabled;
        $this->options['clusterRadius'] = $radius;
        $this->options['clusterMaxZoom'] = $maxZoom;

        return $this;
    }

    public function interactive(bool $enabled = true): self
    {
        $this->options['interactive'] = $enabled;

        return $this;
    }

    public function visible(bool $visible = true): self
    {
        $this->options['visible'] = $visible;

        return $this;
    }

    public function heatmapRadius(float $pixels): self
    {
        $this->options['heatmapRadius'] = $pixels;

        return $this;
    }

    public function heatmapIntensity(float $intensity): self
    {
        $this->options['heatmapIntensity'] = $intensity;

        return $this;
    }

    /** @param list<string> $colors */
    public function heatmapColors(array $colors): self
    {
        $this->options['colors'] = $colors;

        return $this;
    }

    /** @param array<mixed> $dash Validated positive numeric lengths. */
    public function lineDashArray(array $dash): self
    {
        if (! array_is_list($dash) || count($dash) < 2 || count($dash) > 8) {
            throw new InvalidArgumentException('A line dash needs two to eight lengths.');
        }
        foreach ($dash as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value) || $value <= 0 || $value > 64) {
                throw new InvalidArgumentException('Invalid line dash length.');
            }
        }
        $this->options['dash'] = $dash;

        return $this;
    }

    /** @param array<mixed> $stops Validated [zoom, radius] pairs. */
    public function heatmapRadiusStops(array $stops): self
    {
        $previous = -1;
        if (! array_is_list($stops) || count($stops) < 2 || count($stops) > 8) {
            throw new InvalidArgumentException('Invalid heatmap zoom stops.');
        }
        foreach ($stops as $stop) {
            if (! is_array($stop) || ! array_is_list($stop) || count($stop) !== 2 || ! is_numeric($stop[0]) || ! is_numeric($stop[1]) || ! is_finite((float) $stop[0]) || ! is_finite((float) $stop[1]) || $stop[0] <= $previous || $stop[0] > 24 || $stop[1] < 1 || $stop[1] > 128) {
                throw new InvalidArgumentException('Invalid heatmap zoom stop.');
            }
            $previous = $stop[0];
        }
        $this->options['radiusStops'] = $stops;

        return $this;
    }

    /** @param array<mixed> $stops Validated [density, #RRGGBB / #RRGGBBAA] pairs. */
    public function heatmapColorStops(array $stops): self
    {
        $previous = -1;
        if (! array_is_list($stops) || count($stops) < 2 || count($stops) > 8) {
            throw new InvalidArgumentException('Invalid heatmap density stops.');
        }
        foreach ($stops as $stop) {
            if (! is_array($stop) || ! array_is_list($stop) || count($stop) !== 2 || ! is_numeric($stop[0]) || ! is_finite((float) $stop[0]) || $stop[0] <= $previous || $stop[0] > 1 || ! is_string($stop[1]) || ! preg_match('/^#[a-fA-F0-9]{6}(?:[a-fA-F0-9]{2})?$/D', $stop[1])) {
                throw new InvalidArgumentException('Invalid heatmap density stop.');
            }
            $previous = $stop[0];
        }
        if ($stops[0][0] != 0 || $stops[count($stops) - 1][0] != 1) {
            throw new InvalidArgumentException('Density stops must span zero to one.');
        }
        $this->options['colorStops'] = $stops;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        foreach (['radius' => [2, 48], 'width' => [.5, 20], 'opacity' => [0, 1], 'clusterRadius' => [20, 100], 'clusterMaxZoom' => [0, 20], 'heatmapRadius' => [1, 128], 'heatmapIntensity' => [.01, 10], 'weightMax' => [.000001, 1000000]] as $key => [$min, $max]) {
            $value = $this->options[$key];
            if (! is_numeric($value) || ! is_finite((float) $value) || $value < $min || $value > $max) {
                throw new InvalidArgumentException('Invalid layer range: '.$key);
            }
        }
        foreach (['labelProperty', 'weightProperty'] as $key) {
            if ($this->options[$key] !== null && ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $this->options[$key])) {
                throw new InvalidArgumentException('Invalid layer property name.');
            }
        }
        $colors = $this->options['colors'];
        if (! array_is_list($colors) || count($colors) < 2 || count($colors) > 8) {
            throw new InvalidArgumentException('Heatmap palette requires two to eight colors.');
        }
        foreach ([$this->options['color'], ...$colors] as $color) {
            if (! is_string($color) || ! preg_match('/^#[a-fA-F0-9]{6}$/D', $color)) {
                throw new InvalidArgumentException('Layer colors require #RRGGBB.');
            }
        }
        if ($this->options['navigationRoute'] ?? false) {
            foreach ($this->options['data']['features'] as $feature) {
                if ($feature['geometry']['type'] !== 'LineString') {
                    throw new InvalidArgumentException('Navigation routes require LineString geometry.');
                }
            }
        }
        if (in_array($this->options['kind'], ['pills', 'tracked'], true) && count($this->options['data']['features']) > 200) {
            throw new InvalidArgumentException('Price markers are limited to 200 DOM markers; use GPU point layers for larger datasets.');
        }
        if (in_array($this->options['kind'], ['heatmap', 'pills', 'tracked'], true) || $this->options['cluster']) {
            foreach ($this->options['data']['features'] as $feature) {
                if ($feature['geometry']['type'] !== 'Point') {
                    throw new InvalidArgumentException('Heatmap, price and clustered layers accept Point features only.');
                }
            }
        }
        if (in_array($this->options['kind'], ['pills', 'tracked'], true) && $this->options['cluster']) {
            throw new InvalidArgumentException('Price pills use their own bounded marker presentation; clustering belongs to GPU point layers.');
        }

        return ['id' => $this->id, ...$this->options];
    }
}
