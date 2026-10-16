<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\PackageArtifactReference;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;

/** @internal Package metadata belongs to the same atomic snapshot as definitions. */
final readonly class CompiledPackageAssets
{
    /** @var array<string, AssetPackage> */
    public array $bindings;

    /** @var array<string, AssetPackage> */
    public array $packages;

    /** @var array<string, PackageArtifactReference> */
    public array $entries;

    /** @var array<string, PackageArtifactReference> */
    public array $chunks;

    /** @var array<string, PackageArtifactReference> */
    public array $styles;

    /** @var array<string, PackageArtifactReference> */
    public array $helpers;

    /** @var array<string, list<string>> */
    public array $eagerChunks;

    /**
     * @param  array<string, AssetPackage>  $bindings  Component => installation.
     * @param  array<string, AssetPackage>  $packages  Composer identity => installation.
     * @param  array<string, PackageArtifactReference>  $entries
     * @param  array<string, PackageArtifactReference>  $chunks
     * @param  array<string, PackageArtifactReference>  $styles
     * @param  array<string, PackageArtifactReference>  $helpers
     * @param  array<string, list<string>>  $eagerChunks  Component => scoped module IDs.
     */
    public function __construct(
        array $bindings = [],
        array $packages = [],
        array $entries = [],
        array $chunks = [],
        array $styles = [],
        array $helpers = [],
        array $eagerChunks = [],
    ) {
        $this->bindings = ArraySnapshot::copy($bindings);
        $this->packages = ArraySnapshot::copy($packages);
        $this->entries = ArraySnapshot::copy($entries);
        $this->chunks = ArraySnapshot::copy($chunks);
        $this->styles = ArraySnapshot::copy($styles);
        $this->helpers = ArraySnapshot::copy($helpers);
        $this->eagerChunks = ArraySnapshot::copy($eagerChunks);
    }
}
