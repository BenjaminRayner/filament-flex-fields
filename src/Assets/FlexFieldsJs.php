<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Assets;

use Bjanczak\FilamentFlexFields\Assets\Concerns\ResolvesAssetContentVersion;
use Bjanczak\FilamentFlexFields\Assets\Concerns\ResolvesImmutableAssetRelease;
use Filament\Support\Assets\Js;

class FlexFieldsJs extends Js
{
    use ResolvesAssetContentVersion;
    use ResolvesImmutableAssetRelease;

    public function getSrc(): string
    {
        $path = $this->immutablePublicPath();

        return $path === null ? parent::getSrc() : asset($path);
    }
}
