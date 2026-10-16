<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Illuminate\Filesystem\Filesystem;

/** Portable packages and builtin assets share the same atomic commit protocol. */
final class PackageReleasePublisher
{
    private readonly ReleaseDirectoryPublisher $publisher;

    public function __construct(Filesystem $filesystem)
    {
        $this->publisher = new ReleaseDirectoryPublisher($filesystem);
    }

    public function preflight(AssetPackage $package, string $publicRoot, string $prefix): void
    {
        $this->publisher->preflight(ReleaseInventory::forPackage($package), $publicRoot, $package->publicDirectory($prefix));
    }

    public function verifyPublished(AssetPackage $package, string $publicRoot, string $prefix): void
    {
        $directory = $this->publisher->destination($publicRoot, $package->publicDirectory($prefix));
        $this->publisher->verify(ReleaseInventory::forPackage($package), $directory);
    }

    /** @return array{copied: int, unchanged: int} */
    public function publish(AssetPackage $package, string $publicRoot, string $prefix): array
    {
        return $this->publisher->publish(ReleaseInventory::forPackage($package), $publicRoot, $package->publicDirectory($prefix));
    }
}
