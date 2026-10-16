<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Contracts;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ManifestIdentity;
use Bjanczak\FilamentFlexFields\Support\Theme\AppearanceNamespace;
use InvalidArgumentException;

/** Semantic installation metadata; no record, tenant, URL or instance configuration. */
final readonly class ComponentPackage
{
    public function __construct(
        public AssetPackage $assets,
        public string $namespace,
        public string $version,
        public int $phpAbi = CoreAbi::PHP,
        public int $jsAbi = CoreAbi::JS,
    ) {
        ManifestIdentity::assert($namespace);
        if (strlen($namespace) > 48 || strlen($version) > 96
            || preg_match('/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/D', $version) !== 1) {
            throw new InvalidArgumentException('Component package needs a bounded namespace and semantic release version.');
        }
        CoreAbi::assertCompatible($phpAbi, $jsAbi);
        $release = explode('+', $version, 2)[0];
        $prerelease = explode('-', $release, 2)[1] ?? null;
        if ($prerelease !== null) {
            foreach (explode('.', $prerelease) as $identifier) {
                if (ctype_digit($identifier) && strlen($identifier) > 1 && $identifier[0] === '0') {
                    throw new InvalidArgumentException('Numeric prerelease versions cannot have leading zeros.');
                }
            }
        }
        foreach ([$assets->manifest->entries, $assets->manifest->styles, $assets->manifest->helpers] as $roots) {
            foreach (array_keys($roots) as $identity) {
                $this->assertOwns($identity);
            }
        }
    }

    public function assertOwns(string $identity): void
    {
        ManifestIdentity::assert($identity);
        if (! str_starts_with($identity, $this->namespace.'-')) {
            throw new InvalidArgumentException('Component identity is outside package namespace: '.$identity);
        }
    }

    public function appearance(): AppearanceNamespace
    {
        return new AppearanceNamespace($this->namespace);
    }

    public function sameContractAs(self $other): bool
    {
        return $this->assets->sameInstallationAs($other->assets)
            && $this->namespace === $other->namespace && $this->version === $other->version
            && $this->phpAbi === $other->phpAbi && $this->jsAbi === $other->jsAbi;
    }

    /** @return array<string, int|string> */
    public function export(): array
    {
        return ['package' => $this->assets->manifest->package, 'namespace' => $this->namespace,
            'version' => $this->version, 'phpAbi' => $this->phpAbi, 'jsAbi' => $this->jsAbi,
            'release' => $this->assets->manifest->release];
    }
}
