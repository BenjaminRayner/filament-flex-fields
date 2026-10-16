<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Closure;

/** @mixin MapPickerField */
trait ConfiguresMapSelection
{
    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|Closure|null  $bounds
     */
    public function allowedBounds(array|Closure|null $bounds): static
    {
        $this->locationMapOptions['allowedBounds'] = $bounds;

        return $this;
    }

    /**
     * @param  array{center: array{0: float, 1: float}, meters: float}|Closure|null  $area
     */
    public function allowedRadius(array|Closure|null $area): static
    {
        $this->locationMapOptions['allowedRadius'] = $area;

        return $this;
    }

    public function selectionMode(string|Closure $mode): static
    {
        $this->locationMapOptions['selectionMode'] = $mode;

        return $this;
    }

    public function multipleMarkers(bool $enabled = true): static
    {
        return $this->selectionMode($enabled ? 'markers' : 'point');
    }

    public function drawZones(bool $enabled = true): static
    {
        return $this->selectionMode($enabled ? 'zones' : 'point');
    }

    /** Mixed Point/LineString/Polygon editor; existing single-purpose modes keep their contracts. */
    public function editMapFeatures(bool $enabled = true): static
    {
        return $this->selectionMode($enabled ? 'features' : 'point');
    }

    /** @param list<string>|Closure $types GeoJSON geometry names. */
    public function allowedGeometryTypes(array|Closure $types): static
    {
        $this->locationMapOptions['allowedGeometryTypes'] = $types;

        return $this;
    }

    public function maxFeatures(int|Closure $limit): static
    {
        $this->locationMapOptions['maxFeatures'] = $limit;

        return $this;
    }

    public function minFeatures(int|Closure $limit): static
    {
        $this->locationMapOptions['minFeatures'] = $limit;

        return $this;
    }

    /** A short list grows naturally; opt into a bounded list for large collections. */
    public function featureListMaxHeight(int|Closure|null $pixels = null): static
    {
        $this->locationMapOptions['featureListMaxHeight'] = $pixels;

        return $this;
    }

    public function clusterMarkers(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['cluster'] = $enabled;

        return $this;
    }

    public function clusterRadius(int|Closure $pixels): static
    {
        $this->locationMapOptions['clusterRadius'] = $pixels;

        return $this;
    }

    public function clusterMaxZoom(int|Closure $zoom): static
    {
        $this->locationMapOptions['clusterMaxZoom'] = $zoom;

        return $this;
    }

    public function clusterMinimumPoints(int|Closure $count): static
    {
        $this->locationMapOptions['clusterMinimumPoints'] = $count;

        return $this;
    }

    public function markerColor(string|Closure $color): static
    {
        $this->locationMapOptions['color'] = $color;

        return $this;
    }

    /**
     * Overlay geometries do not become submitted form state.
     *
     * @param  array<string, mixed>|Closure  $geojson
     */
    public function mapFeatures(array|Closure $geojson): static
    {
        $this->locationMapOptions['features'] = $geojson;

        return $this;
    }

    public function showLegend(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['legend'] = $enabled;

        return $this;
    }

    public function featurePopups(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['featurePopups'] = $enabled;

        return $this;
    }

    public function featureTooltips(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['featureTooltips'] = $enabled;

        return $this;
    }

    public function pointSize(int|Closure $pixels): static
    {
        $this->locationMapOptions['pointSize'] = $pixels;

        return $this;
    }

    public function showZoneMetrics(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['zoneMetrics'] = $enabled;

        return $this;
    }

    public function legendTitle(string|Closure|null $title): static
    {
        $this->locationMapOptions['legendTitle'] = $title;

        return $this;
    }

    public function legendDescription(string|Closure|null $description): static
    {
        $this->locationMapOptions['legendDescription'] = $description;

        return $this;
    }
}
