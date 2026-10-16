<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Closure;

/** @mixin MapPickerField */
trait ConfiguresZoneEditor
{
    public function preventZoneOverlap(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['preventZoneOverlap'] = $enabled;

        return $this;
    }

    public function allowZoneTouching(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['allowZoneTouching'] = $enabled;

        return $this;
    }

    public function collisionWithOverlays(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['collisionWithOverlays'] = $enabled;

        return $this;
    }

    public function snapToVertices(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['snapToVertices'] = $enabled;

        return $this;
    }

    public function snapToSegments(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['snapToSegments'] = $enabled;

        return $this;
    }

    public function measurementUnit(string|Closure $unit): static
    {
        $this->locationMapOptions['measurementUnit'] = $unit;

        return $this;
    }

    public function resizableZones(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['resizableZones'] = $enabled;

        return $this;
    }

    public function showShapeDimensions(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['shapeDimensions'] = $enabled;

        return $this;
    }

    public function circleSegments(int|Closure $segments): static
    {
        $this->locationMapOptions['circleSegments'] = $segments;

        return $this;
    }

    public function minCircleRadius(int|float|Closure $meters): static
    {
        $this->locationMapOptions['minCircleRadius'] = $meters;

        return $this;
    }

    public function maxCircleRadius(int|float|Closure $meters): static
    {
        $this->locationMapOptions['maxCircleRadius'] = $meters;

        return $this;
    }

    public function maxLineVertices(int|Closure $limit): static
    {
        $this->locationMapOptions['maxLineVertices'] = $limit;

        return $this;
    }

    public function geometryStrokeWidth(int|float|Closure $pixels): static
    {
        $this->locationMapOptions['geometryStrokeWidth'] = $pixels;

        return $this;
    }

    public function zoneFillOpacity(int|float|Closure $opacity): static
    {
        $this->locationMapOptions['zoneFillOpacity'] = $opacity;

        return $this;
    }

    /**
     * @param  list<string>|Closure  $tools
     */
    public function drawingTools(array|Closure $tools): static
    {
        $this->locationMapOptions['drawingTools'] = $tools;

        return $this;
    }

    public function editableZones(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['editableZones'] = $enabled;

        return $this;
    }

    public function deletableFeatures(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['deletableFeatures'] = $enabled;

        return $this;
    }

    public function drawingHistoryLimit(int|Closure $limit): static
    {
        $this->locationMapOptions['historyLimit'] = $limit;

        return $this;
    }

    public function snapDistance(int|Closure $pixels): static
    {
        $this->locationMapOptions['snapDistance'] = $pixels;

        return $this;
    }

    public function maxZoneVertices(int|Closure $limit): static
    {
        $this->locationMapOptions['maxZoneVertices'] = $limit;

        return $this;
    }

    public function minZoneArea(int|float|Closure $squareMeters): static
    {
        $this->locationMapOptions['minZoneArea'] = $squareMeters;

        return $this;
    }

    public function maxZoneArea(int|float|Closure $squareMeters): static
    {
        $this->locationMapOptions['maxZoneArea'] = $squareMeters;

        return $this;
    }

    /**
     * @param  list<string>|Closure  $colors
     */
    public function zoneColors(array|Closure $colors): static
    {
        $this->locationMapOptions['zoneColors'] = $colors;

        return $this;
    }

    public function requireZoneLabels(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['requireZoneLabels'] = $enabled;

        return $this;
    }
}
