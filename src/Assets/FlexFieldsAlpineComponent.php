<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Assets;

use Bjanczak\FilamentFlexFields\Assets\Concerns\ResolvesAssetContentVersion;
use Bjanczak\FilamentFlexFields\Assets\Concerns\ResolvesImmutableAssetRelease;
use Filament\Support\Assets\AlpineComponent;

class FlexFieldsAlpineComponent extends AlpineComponent
{
    use ResolvesAssetContentVersion;
    use ResolvesImmutableAssetRelease;

    protected bool $hasContentHash = false;

    public function contentHashed(bool $condition = true): static
    {
        $this->hasContentHash = $condition;

        return $this;
    }

    public function getSrc(): string
    {
        // ESM imports address hashed chunks by filename without a query string.
        // Preload must use exactly that same module identity.
        return $this->hasContentHash || $this->immutablePublicPath() !== null
            ? asset($this->getRelativePublicPath())
            : parent::getSrc();
    }
}
