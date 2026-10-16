<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Closure;

/** @mixin MapPickerField */
trait ConfiguresMapInteraction
{
    public function draggableMarkers(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['draggable'] = $enabled;

        return $this;
    }

    public function selectOnMapClick(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['selectOnClick'] = $enabled;

        return $this;
    }

    public function reverseGeocoding(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['reverseGeocoding'] = $enabled;

        return $this;
    }

    public function geolocation(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['geolocation'] = $enabled;

        return $this;
    }

    public function geolocationHighAccuracy(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['highAccuracy'] = $enabled;

        return $this;
    }

    public function geolocationTimeout(int|Closure $milliseconds): static
    {
        $this->locationMapOptions['geolocationTimeout'] = $milliseconds;

        return $this;
    }

    public function manualCoordinates(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['manualCoordinates'] = $enabled;

        return $this;
    }

    public function clearable(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['clearable'] = $enabled;

        return $this;
    }

    public function zoomControls(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['zoomControls'] = $enabled;

        return $this;
    }

    public function fitControl(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['fitControl'] = $enabled;

        return $this;
    }

    public function compactMapControls(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['compactControls'] = $enabled;

        return $this;
    }

    public function compass(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['compass'] = $enabled;

        return $this;
    }

    public function fullscreen(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['fullscreen'] = $enabled;

        return $this;
    }

    public function scale(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['scale'] = $enabled;

        return $this;
    }

    public function scaleUnit(string|Closure $unit): static
    {
        $this->locationMapOptions['scaleUnit'] = $unit;

        return $this;
    }

    public function scrollZoom(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['scrollZoom'] = $enabled;

        return $this;
    }

    public function cooperativeGestures(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['cooperativeGestures'] = $enabled;

        return $this;
    }
}
