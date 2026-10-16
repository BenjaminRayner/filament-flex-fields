<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Manifest;

use InvalidArgumentException;
use RuntimeException;

/** Immutable local installation of a portable manifest; no request or URL state. */
final readonly class AssetPackage
{
    public string $sourceRoot;

    public function __construct(public AssetManifest $manifest, string $sourceRoot)
    {
        $this->sourceRoot = ArtifactPath::root($sourceRoot);
    }

    public function sourcePath(string $artifact): string
    {
        $path = $this->manifest->artifact($artifact)->path;
        try {
            return ArtifactPath::source($this->sourceRoot, $path);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new InvalidArgumentException('Missing or unsafe installed artifact: '.$this->manifest->package.' -> '.$artifact.' -> '.$path, previous: $exception);
        }
    }

    public function publicDirectory(string $prefix = ''): string
    {
        $prefix = trim($prefix, '/');
        if (strlen($prefix) > 512 || ($prefix !== '' && preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $prefix) !== 1)) {
            throw new InvalidArgumentException('Manifest asset prefix must be a safe relative directory.');
        }
        if ($prefix !== '') {
            ArtifactPath::assert($prefix);
        }

        return ($prefix === '' ? '' : $prefix.'/').'filament-flex-fields-assets/packages/'.$this->manifest->package.'/'.$this->manifest->release;
    }

    public function sameInstallationAs(self $other): bool
    {
        return $this->manifest->package === $other->manifest->package
            && $this->manifest->release === $other->manifest->release
            && $this->sourceRoot === $other->sourceRoot;
    }

    /** Asset IDs are package-scoped; URLs additionally carry the release identity. */
    public function moduleIdentity(string $artifact): string
    {
        $this->manifest->artifact($artifact);

        return 'fff-package-'.hash('sha256', $this->manifest->package).'-'.$artifact;
    }
}
