<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Assets;

use Bjanczak\FilamentFlexFields\Assets\Concerns\ResolvesAssetContentVersion;
use Bjanczak\FilamentFlexFields\Assets\Concerns\ResolvesImmutableAssetRelease;
use Filament\Support\Assets\Css;

class FlexFieldsCss extends Css
{
    use ResolvesAssetContentVersion;
    use ResolvesImmutableAssetRelease;

    public function getHref(): string
    {
        $path = $this->immutablePublicPath();

        return $path === null ? parent::getHref() : asset($path);
    }
}
