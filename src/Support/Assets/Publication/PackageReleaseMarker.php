<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifestCompiler;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use RuntimeException;

/** Historical releases are verified without requiring their old source checkout. */
final class PackageReleaseMarker
{
    public static function inventory(string $directory, string $release, string $package): ReleaseInventory
    {
        $path = $directory.'/.release.json';
        if (is_link($path) || ! is_file($path)) {
            throw new RuntimeException('Missing or unsafe portable release marker.');
        }
        $manifest = (new AssetManifestCompiler)->load($path);
        if ($manifest->package !== $package || $manifest->release !== $release) {
            throw new RuntimeException('Portable release marker does not match its package and directory.');
        }

        return ReleaseInventory::forPackage(new AssetPackage($manifest, $directory));
    }
}
