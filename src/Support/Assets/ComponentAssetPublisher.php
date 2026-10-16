<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Assets\Contracts\AssetPublicationAlias;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\AssetBuildValidator;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PackageReleasePublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PublicationLock;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\ReleaseDirectoryPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\ReleaseInventory;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/** One publisher for registered CSS/JS and component-owned binary resources. */
final class ComponentAssetPublisher
{
    public function __construct(private readonly ComponentRegistry $registry, private readonly Filesystem $filesystem) {}

    /** @return array{copied: int, unchanged: int} */
    public function publish(?string $publicRoot = null): array
    {
        (new AssetBuildValidator)->validate($this->registry)->assertValid();

        return PublicationLock::run($publicRoot ?? public_path(), fn (string $root): array => $this->publishLocked($root, $root.'/filament-flex-fields-assets/.published.json'));
    }

    /** @return array{copied: int, unchanged: int} */
    private function publishLocked(string $root, string $stampPath): array
    {
        $stamps = AssetVersionManifest::readPublicationStamps($root);
        $originalStamps = $stamps;
        $files = $this->plan($root);
        $result = ['copied' => 0, 'unchanged' => 0];
        $packagePublisher = new PackageReleasePublisher($this->filesystem);
        $prefix = config('filament.assets_path') ?? '';
        if (! is_string($prefix)) {
            throw new RuntimeException('Filament asset prefix must be a string.');
        }
        $builtin = new BuiltInAssetRelease(new AssetVersionManifest(allowDevelopmentFallback: false));
        $files = array_values(array_filter($files, static fn (array $file): bool => $builtin->relativeSource($file[0]) === null));
        $plannedStamps = $stamps;
        foreach ($files as [$source, $destination, $digest]) {
            $size = filesize($source);
            $modified = filemtime($source);
            if ($size === false || $modified === false) {
                throw new RuntimeException('Cannot read compatibility asset publication metadata.');
            }
            $plannedStamps[substr($destination, strlen($root) + 1)] = ['digest' => $digest, 'size' => $size, 'modified' => $modified];
        }
        AssetVersionManifest::encodePublicationStamps($plannedStamps);
        $releasePublisher = new ReleaseDirectoryPublisher($this->filesystem);
        $inventory = $builtin->inventory();
        $builtinDirectory = $builtin->publicDirectory($prefix);
        $ownsBuiltin = $this->ownsBuiltinAssets($builtin, $inventory->sourceRoot);
        if ($ownsBuiltin) {
            $releasePublisher->preflight($inventory, $root, $builtinDirectory);
        }
        $reserved = $root.'/'.ReleaseInventory::prefix($prefix).'filament-flex-fields-assets/core';
        foreach ($files as [, $destination]) {
            if ($destination === $reserved || str_starts_with($destination, $reserved.'/') || str_starts_with($reserved, $destination.'/')) {
                throw new RuntimeException('Legacy asset overlaps the reserved builtin release tree.');
            }
        }
        $packages = $this->registry->packageAssets()->packages;
        foreach ($packages as $package) {
            $directory = $root.'/'.$package->publicDirectory($prefix);
            foreach ($files as [, $destination]) {
                if ($destination === $directory || str_starts_with($destination, $directory.'/')
                    || str_starts_with($directory, $destination.'/')) {
                    throw new RuntimeException('Legacy asset overlaps an immutable package release.');
                }
            }
            $packagePublisher->preflight($package, $root, $prefix);
        }
        foreach ($packages as $package) {
            $published = $packagePublisher->publish($package, $root, $prefix);
            $result['copied'] += $published['copied'];
            $result['unchanged'] += $published['unchanged'];
        }
        if ($ownsBuiltin) {
            $published = $releasePublisher->publish($inventory, $root, $builtinDirectory);
            $result['copied'] += $published['copied'];
            $result['unchanged'] += $published['unchanged'];
        }

        foreach ($files as [$source, $destination, $digest]) {
            $relative = substr($destination, strlen($root) + 1);
            clearstatcache(true, $destination);
            if (is_file($destination) && hash_file('sha256', $destination) === $digest) {
                $result['unchanged']++;
            } else {
                $this->filesystem->ensureDirectoryExists(dirname($destination));
                $this->assertDestination($root, $destination);
                $this->replace($source, $destination, $digest);
                $result['copied']++;
            }
            clearstatcache(true, $destination);
            $size = filesize($destination);
            $modified = filemtime($destination);
            if ($size === false || $modified === false) {
                throw new RuntimeException('Cannot read published compatibility asset metadata.');
            }
            $stamps[$relative] = ['digest' => $digest, 'size' => $size, 'modified' => $modified];
        }

        if ($stamps !== $originalStamps) {
            $this->filesystem->replace($stampPath, AssetVersionManifest::encodePublicationStamps($stamps));
        }

        return $result;
    }

    /** Isolated external-only registries do not publish unrelated builtin bytes. */
    private function ownsBuiltinAssets(BuiltInAssetRelease $builtin, string $dist): bool
    {
        foreach ([...FilamentAsset::getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]), ...FilamentAsset::getScripts([FilamentFlexFieldsPlugin::PACKAGE_NAME]), ...FilamentAsset::getAlpineComponents([FilamentFlexFieldsPlugin::PACKAGE_NAME])] as $asset) {
            if ($builtin->relativeSource($asset->getPath()) !== null) {
                return true;
            }
        }
        foreach ($this->registry->all() as $definition) {
            foreach ($definition->publications as $resource) {
                $source = realpath($resource->sourcePath());
                if ($source !== false && ($source === $dist || str_starts_with($source, $dist.'/'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<array{string, string, string}> */
    private function plan(string $root): array
    {
        $manifest = new AssetVersionManifest;
        $builtin = new BuiltInAssetRelease($manifest);
        $files = [];
        foreach ([...FilamentAsset::getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]), ...FilamentAsset::getScripts([FilamentFlexFieldsPlugin::PACKAGE_NAME]), ...FilamentAsset::getAlpineComponents([FilamentFlexFieldsPlugin::PACKAGE_NAME])] as $asset) {
            if ($asset->isRemote() && $builtin->relativeSource($asset->getPath()) === null) {
                continue;
            }
            $source = $asset->getPath();
            if ($source === null) {
                continue;
            }
            if (! is_file($source)) {
                throw new RuntimeException('Missing registered asset: '.$asset->getId());
            }
            $alias = $asset instanceof AssetPublicationAlias ? $asset->getPublicationOwnerId() : null;
            if ($alias !== null && preg_match('/^[a-z0-9][a-z0-9.-]{0,254}$/D', $alias) !== 1) {
                throw new RuntimeException('Invalid publication alias owner.');
            }
            $files[] = [$source, $root.'/'.$asset->getRelativePublicPath(), $asset->getId(), $alias];
        }
        foreach ($this->registry->all() as $definition) {
            foreach ($definition->publications as $resource) {
                $source = realpath($resource->sourcePath());
                if ($source === false || ! is_dir($source)) {
                    throw new RuntimeException('Missing resource directory: '.$definition->id.'/'.$resource->id);
                }
                foreach ($this->filesystem->allFiles($source) as $file) {
                    if ($file->isLink() || ! preg_match('/\.(?:mp3|wav|ogg|webm|mp4|png|jpg|jpeg|webp|avif|gif|svg|json|wasm|bin|onnx|js|css|woff2?)$/iD', $file->getFilename())) {
                        continue;
                    }
                    $resolvedFile = realpath($file->getPathname());
                    if ($resolvedFile === false || ! str_starts_with($resolvedFile, $source.'/')) {
                        throw new RuntimeException('Resource file escaped its declared source directory.');
                    }
                    if ($manifest->versionFor($resolvedFile) !== null) {
                        continue;
                    }
                    $relative = substr($file->getPathname(), strlen($source) + 1);
                    $files[] = [$file->getPathname(), $root.'/'.$resource->publicDirectory.'/'.$relative, null, null];
                }
            }
        }
        $plan = [];
        $targets = [];
        foreach ($files as [$source, $destination, $id, $alias]) {
            $this->assertDestination($root, $destination);
            $canonicalSource = realpath($source);
            $digest = hash_file('sha256', $source);
            if ($canonicalSource === false || $digest === false) {
                throw new RuntimeException('Cannot resolve or hash source asset: '.$source);
            }
            if (isset($targets[$destination])) {
                [$priorSource, $priorDigest, $priorId, $priorAlias, $paired] = $targets[$destination];
                $matchesOwner = $alias !== null && $priorAlias === null && $alias === $priorId;
                $matchesAlias = $priorAlias !== null && $alias === null && $priorAlias === $id;
                if (! $paired && ($matchesOwner || $matchesAlias)
                    && $priorSource === $canonicalSource && $priorDigest === $digest) {
                    $targets[$destination][4] = true;

                    continue;
                }
                throw new RuntimeException('Multiple asset sources target the same public file.');
            }
            $targets[$destination] = [$canonicalSource, $digest, $id, $alias, false];
            $declaredVersion = $manifest->versionFor($source);
            if ($declaredVersion !== null && $declaredVersion !== $digest) {
                throw new RuntimeException('Source assets differ from the version manifest. Rebuild before publishing.');
            }
            $plan[] = [$source, $destination, $digest];
        }
        foreach ($targets as [, , , $alias, $paired]) {
            if ($alias !== null && ! $paired) {
                throw new RuntimeException('Publication alias has no matching registered owner.');
            }
        }

        return $plan;
    }

    private function assertDestination(string $root, string $destination): void
    {
        if (! str_starts_with($destination, $root.'/') || str_contains($destination, '/../') || is_link($destination)) {
            throw new RuntimeException('Asset destination escaped the public root or contains an unsafe symlink.');
        }
        $parent = dirname($destination);
        while (! file_exists($parent) && ! is_link($parent)) {
            $parent = dirname($parent);
        }
        $resolved = realpath($parent);
        if ($resolved === false || ! str_starts_with($resolved.'/', $root.'/')) {
            throw new RuntimeException('Asset destination contains an unsafe symlink.');
        }
    }

    private function replace(string $source, string $destination, string $digest): void
    {
        $temporary = tempnam(dirname($destination), '.fff-publish-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot allocate asset publication file.');
        }
        try {
            if (! $this->filesystem->copy($source, $temporary) || hash_file('sha256', $temporary) !== $digest
                || ! rename($temporary, $destination)) {
                throw new RuntimeException('Cannot publish asset: '.$destination);
            }
            chmod($destination, 0644);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
