<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use InvalidArgumentException;

/** Aggregate metadata limits apply before filesystem traversal of installed outputs. */
final class PackageAssetBudget
{
    public const MAX_PACKAGES = 128;

    public const MAX_ARTIFACTS = 16384;

    public const MAX_EDGES = 131072;

    /** @param array<string, AssetPackage> $bindings */
    public function validate(array $bindings): void
    {
        $seen = [];
        $artifacts = 0;
        $edges = 0;
        foreach ($bindings as $package) {
            if (! $package instanceof AssetPackage) {
                throw new InvalidArgumentException('Asset budget requires typed packages.');
            }
            $manifest = $package->manifest;
            if (isset($seen[$manifest->package])) {
                continue;
            }
            $seen[$manifest->package] = true;
            $artifacts += count($manifest->artifacts);
            foreach ($manifest->artifacts as $artifact) {
                $edges += count($artifact->imports) + count($artifact->dynamicImports) + count($artifact->resources);
            }
            if (count($seen) > self::MAX_PACKAGES || $artifacts > self::MAX_ARTIFACTS || $edges > self::MAX_EDGES) {
                throw new InvalidArgumentException('Installed package graph exceeds the aggregate asset metadata budget.');
            }
        }
    }
}
