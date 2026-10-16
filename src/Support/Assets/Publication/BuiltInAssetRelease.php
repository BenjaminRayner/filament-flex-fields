<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use RuntimeException;

/** One scoped seal projects the entire builtin distribution to immutable URLs. */
final class BuiltInAssetRelease
{
    private readonly AssetVersionManifest $seal;

    private readonly string|false $distRoot;

    /** @var array<string, string|null> */
    private array $sourcePaths = [];

    public function __construct(?AssetVersionManifest $seal = null)
    {
        $this->seal = $seal ?? RequestState::resolve(AssetVersionManifest::class);
        $this->distRoot = realpath($this->seal->root().'/resources/dist');
    }

    public function relativeSource(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }
        if (array_key_exists($source, $this->sourcePaths)) {
            return $this->sourcePaths[$source];
        }
        $resolved = realpath($source);
        $root = $this->distRoot;
        if ($resolved === false || $root === false || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            if (count($this->sourcePaths) < 512 && strlen($source) <= 4096) {
                $this->sourcePaths[$source] = null;
            }

            return null;
        }
        $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($resolved, strlen($root) + 1));
        if ($this->seal->digestFor($path) === null) {
            throw new RuntimeException('Builtin asset is absent from the sealed release. Rebuild before publishing.');
        }

        if (count($this->sourcePaths) < 512 && strlen($source) <= 4096) {
            $this->sourcePaths[$source] = $path;
        }

        return $path;
    }

    public function publicDirectory(string $prefix = ''): string
    {
        $release = $this->seal->release() ?? throw new RuntimeException('Cannot resolve the builtin asset release.');

        return ReleaseInventory::prefix($prefix).'filament-flex-fields-assets/core/'.$release;
    }

    public function publicPath(?string $source, string $prefix = ''): ?string
    {
        $path = $this->relativeSource($source);

        return $path === null ? null : $this->publicDirectory($prefix).'/'.$path;
    }

    public function inventory(): ReleaseInventory
    {
        $files = [];
        foreach ($this->seal->assets() as $path => $hash) {
            $files[$path] = ['sha256' => $hash, 'bytes' => $this->seal->bytesFor($path) ?? throw new RuntimeException('Missing release artifact size.')];
        }
        $marker = json_encode(['format' => 1, 'kind' => 'core', 'release' => $this->seal->release(),
            'assets' => $this->seal->assets(), 'bytes' => array_map(static fn (array $file): int => $file['bytes'], $files),
            'metadata' => $this->seal->metadata()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;

        return new ReleaseInventory($this->seal->root().'/resources/dist', $files, $marker);
    }
}
