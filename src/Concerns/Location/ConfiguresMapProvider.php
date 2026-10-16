<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Closure;

/** @mixin MapPickerField */
trait ConfiguresMapProvider
{
    /** Gray urban blocks, white streets and green parks; opt-in per map instance. */
    public function deliveryMapAppearance(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['deliveryAppearance'] = $enabled;

        return $this;
    }

    public function mapProvider(string|Closure $provider): static
    {
        $this->locationMapOptions['provider'] = $provider;

        return $this;
    }

    /**
     * @param  string|array<string, mixed>|Closure|null  $style
     */
    public function mapStyle(string|array|Closure|null $style): static
    {
        $this->locationMapOptions['style'] = $style;

        return $this;
    }

    /**
     * @param  array{light: string, dark: string}|Closure  $styles
     */
    public function mapStyles(array|Closure $styles): static
    {
        $this->locationMapOptions['styles'] = $styles;

        return $this;
    }

    public function mapTheme(string|Closure $theme): static
    {
        $this->locationMapOptions['theme'] = $theme;

        return $this;
    }

    public function lazyMap(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['lazy'] = $enabled;

        return $this;
    }

    public function mapWorkerUrl(string|Closure|null $url): static
    {
        $this->locationMapOptions['workerUrl'] = $url;

        return $this;
    }

    /**
     * @param  array{script: string, stylesheet: string, module?: bool}|Closure  $assets
     */
    public function mapAssets(array|Closure $assets): static
    {
        $this->locationMapOptions['assets'] = $assets;

        return $this;
    }
}
