<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Assets\Concerns;

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;

trait ResolvesAssetContentVersion
{
    public function getVersion(): string
    {
        if ($this->getPath() === null || parent::isRemote()) {
            return parent::getVersion();
        }
        $manifest = RequestState::resolve(AssetVersionManifest::class);
        $version = $manifest->versionFor($this->getPath());

        if ($version !== null) {
            return $version;
        }

        return $manifest->publishedVersionFor($this->getRelativePublicPath());
    }
}
