<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Manifest;

/** A portable artifact reference. Resolve URL prefixes only in the current context. */
final readonly class PackageArtifactReference
{
    public AssetArtifact $artifact;

    public function __construct(public AssetPackage $package, public string $id)
    {
        $this->artifact = $package->manifest->artifact($id);
    }

    public function relativePublicPath(string $prefix = ''): string
    {
        return $this->package->publicDirectory($prefix).'/'.$this->artifact->path;
    }
}
