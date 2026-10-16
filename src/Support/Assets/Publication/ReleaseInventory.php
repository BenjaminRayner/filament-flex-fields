<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactPath;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use InvalidArgumentException;

/** Verified build metadata; source bytes are checked only at deployment. */
final readonly class ReleaseInventory
{
    public static function forPackage(AssetPackage $package): self
    {
        $files = [];
        foreach ($package->manifest->artifacts as $artifact) {
            $files[$artifact->path] = ['sha256' => $artifact->sha256, 'bytes' => $artifact->bytes];
        }

        return new self($package->sourceRoot, $files, $package->manifest->toJson());
    }

    public string $sourceRoot;

    /** @param array<string, array{sha256: string, bytes: int}> $files */
    public function __construct(string $sourceRoot, public array $files, public string $marker)
    {
        $this->sourceRoot = ArtifactPath::root($sourceRoot);
        if ($files === [] || count($files) > 4096 || strlen($marker) > 4194304 || $marker === '') {
            throw new InvalidArgumentException('Release inventory must be nonempty and bounded.');
        }
        $paths = [];
        $extensions = array_unique(array_merge(...array_map(static fn (ArtifactKind $kind): array => $kind->extensions(), ArtifactKind::cases())));
        foreach ($files as $path => $file) {
            if (! is_string($path) || ! is_array($file) || array_keys($file) !== ['sha256', 'bytes'] || ! is_string($file['sha256']) || ! is_int($file['bytes'])) {
                throw new InvalidArgumentException('Release inventory requires typed artifact metadata.');
            }
            ArtifactPath::assert($path);
            if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) {
                throw new InvalidArgumentException('Unsupported public release artifact type.');
            }
            if (isset($paths[strtolower($path)]) || preg_match('/^[a-f0-9]{64}$/D', $file['sha256']) !== 1 || $file['bytes'] < 0) {
                throw new InvalidArgumentException('Invalid or colliding release artifact.');
            }
            $paths[strtolower($path)] = true;
        }
        $directories = [];
        foreach (array_keys($files) as $path) {
            for ($parent = dirname($path); $parent !== '.'; $parent = dirname($parent)) {
                $key = strtolower($parent);
                if (isset($paths[$key]) || (isset($directories[$key]) && $directories[$key] !== $parent)) {
                    throw new InvalidArgumentException('Release file/directory or case collision.');
                }
                $directories[$key] = $parent;
            }
        }
    }

    public static function prefix(string $prefix): string
    {
        $prefix = trim($prefix, '/');
        if (strlen($prefix) > 512 || ($prefix !== '' && preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $prefix) !== 1)) {
            throw new InvalidArgumentException('Asset prefix must be a safe relative directory.');
        }
        if ($prefix !== '') {
            ArtifactPath::assert($prefix);
        }

        return $prefix === '' ? '' : $prefix.'/';
    }
}
