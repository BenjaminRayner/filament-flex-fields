<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\PackageArtifactReference;

/** Build/export projection. Origins stay explicit; it never creates host URLs. */
final class PortableResourceCatalog
{
    /** @var array<string, array<string, mixed>> */
    private array $resources = [];

    public function __construct(private readonly AssetVersionManifest $versions = new AssetVersionManifest) {}

    public function package(PackageArtifactReference $reference): string
    {
        $manifest = $reference->package->manifest;
        $id = 'package:'.$manifest->package.':'.$manifest->release.':'.$reference->id;
        $artifact = $reference->artifact;
        $this->resources[$id] = [
            'origin' => 'package-release', 'package' => $manifest->package,
            'release' => $manifest->release, 'artifact' => $reference->id,
            'path' => $artifact->path, 'type' => $artifact->type->value,
            'sha256' => $artifact->sha256, 'bytes' => $artifact->bytes,
        ];

        return $id;
    }

    public function distribution(string $path, string $type): string
    {
        $id = 'distribution:'.$path;
        $this->resources[$id] = [
            'origin' => 'distribution', 'package' => 'janczakb/filament-flex-fields',
            'path' => $path, 'type' => $type,
            'sha256' => $this->versions->digestFor($path), 'bytes' => $this->versions->bytesFor($path),
        ];

        return $id;
    }

    /** Compatibility registrations have no portable source graph: report this explicitly. */
    public function registered(string $identity, string $type): string
    {
        $id = 'registered:'.$type.':'.$identity;
        $this->resources[$id] = ['origin' => 'registered', 'identity' => $identity, 'type' => $type];

        return $id;
    }

    /** @return array<string, array<string, mixed>> */
    public function export(): array
    {
        $resources = $this->resources;
        ksort($resources, SORT_STRING);

        return $resources;
    }
}
