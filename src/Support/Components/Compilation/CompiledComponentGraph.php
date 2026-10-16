<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ResolvedChildSlot;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/**
 * Value-free, immutable dependency projections for one registry revision.
 *
 * @internal Produced by ComponentGraphCompiler, not by extension authors.
 */
final readonly class CompiledComponentGraph
{
    /** @var array<string, ComponentDefinition> */
    private array $definitions;

    /** @var array<string, array<string, ResolvedChildSlot>> */
    public array $children;

    /**
     * @param  array<string, ComponentDefinition>  $definitions
     * @param  array<string, array<string, ResolvedChildSlot>>  $children
     */
    public function __construct(
        array $definitions,
        private ComponentProjections $projections,
        public CompiledPackageAssets $packageAssets = new CompiledPackageAssets,
        public CompiledComponentFamilies $families = new CompiledComponentFamilies,
        array $children = [],
    ) {
        $this->definitions = ArraySnapshot::copy($definitions);
        $this->children = ArraySnapshot::copy($children);
    }

    /** @return array<string, ComponentDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /** @return list<string> */
    public function stylesFor(string $id): array
    {
        return $this->projections->stylesFor($id);
    }

    /** @return list<string> */
    public function runtimeComponents(string $id): array
    {
        return $this->projections->runtimeComponents($id);
    }

    public function childSlot(string $parent, string $slot): ResolvedChildSlot
    {
        return $this->children[$parent][$slot] ?? throw new InvalidArgumentException('Unknown child slot: '.$parent.' -> '.$slot);
    }
}
