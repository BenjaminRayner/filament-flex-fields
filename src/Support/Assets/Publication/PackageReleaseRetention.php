<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Illuminate\Filesystem\Filesystem;

/** Retain one installed package's immutable history independently of other families. */
final class PackageReleaseRetention
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /** @param list<string> $protected
     * @return list<array{release: string, publishedAt: int, protected: bool, eligible: bool, removed: bool}>
     */
    public function run(string $publicRoot, string $prefix, AssetPackage $current, int $keep = 3, int $minimumAge = 2592000, array $protected = [], bool $prune = false): array
    {
        $package = $current->manifest->package;

        return (new ReleaseHistoryRetention($this->filesystem))->run(
            $publicRoot,
            dirname($current->publicDirectory($prefix)),
            $current->manifest->release,
            static fn (string $directory, string $release): ReleaseInventory => PackageReleaseMarker::inventory($directory, $release, $package),
            $keep,
            $minimumAge,
            $protected,
            $prune,
        );
    }
}
