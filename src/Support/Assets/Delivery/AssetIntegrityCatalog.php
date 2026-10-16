<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Delivery;

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\PackageArtifactReference;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use InvalidArgumentException;

/** Sealed metadata only: no request-time content hashing or directory traversal. */
final class AssetIntegrityCatalog
{
    /** @return array<string, string> */
    public function export(): array
    {
        $versions = app(AssetVersionManifest::class);
        $integrity = [];
        foreach ([...FilamentAsset::getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]),
            ...FilamentAsset::getScripts([FilamentFlexFieldsPlugin::PACKAGE_NAME]),
            ...FilamentAsset::getAlpineComponents([FilamentFlexFieldsPlugin::PACKAGE_NAME])] as $asset) {
            $hash = $versions->versionFor($asset->getPath());
            if ($hash !== null) {
                $url = $asset instanceof Css ? $asset->getHref() : $asset->getSrc();
                $this->add($integrity, $url, $hash);
            }
        }
        foreach (ComponentAssetCatalog::registry()->packageAssets()->packages as $package) {
            foreach ($package->manifest->artifacts as $id => $artifact) {
                if (! in_array($artifact->type->value, ['module', 'stylesheet', 'script'], true)) {
                    continue;
                }
                $reference = new PackageArtifactReference($package, $id);
                $this->add($integrity, FlexFieldAssets::packageArtifactUrl($reference), $artifact->sha256);
            }
        }
        ksort($integrity, SORT_STRING);

        return $integrity;
    }

    /** @param array<string, string> $integrity */
    private function add(array &$integrity, string $url, string $hex): void
    {
        $bytes = hex2bin($hex);
        if ($bytes === false || strlen($bytes) !== 32) {
            throw new InvalidArgumentException('Invalid sealed asset SHA-256 digest.');
        }
        $hash = 'sha256-'.base64_encode($bytes);
        if (isset($integrity[$url]) && $integrity[$url] !== $hash) {
            throw new InvalidArgumentException('Conflicting sealed asset integrity identities.');
        }
        $integrity[$url] = $hash;
    }
}
