<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Closure;

/** @mixin MapPickerField */
trait ConfiguresMapCamera
{
    public function mapHeight(int|Closure $pixels): static
    {
        $this->locationMapOptions['height'] = $pixels;

        return $this;
    }

    public function minZoom(int|float|Closure $zoom): static
    {
        $this->locationMapOptions['minZoom'] = $zoom;

        return $this;
    }

    public function maxZoom(int|float|Closure $zoom): static
    {
        $this->locationMapOptions['maxZoom'] = $zoom;

        return $this;
    }

    public function pitch(int|float|Closure $degrees): static
    {
        $this->locationMapOptions['pitch'] = $degrees;

        return $this;
    }

    public function bearing(int|float|Closure $degrees): static
    {
        $this->locationMapOptions['bearing'] = $degrees;

        return $this;
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|Closure|null  $bounds
     */
    public function mapBounds(array|Closure|null $bounds): static
    {
        $this->locationMapOptions['bounds'] = $bounds;

        return $this;
    }

    public function fitFeatures(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['fitFeatures'] = $enabled;

        return $this;
    }

    public function animationDuration(int|Closure $milliseconds): static
    {
        $this->locationMapOptions['animationDuration'] = $milliseconds;

        return $this;
    }
}
