<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\Routing\ComponentRouteCompiler;
use InvalidArgumentException;
use LogicException;

/** Compile structural definitions once; never retain render or request context. */
final class ComponentGraphCompiler
{
    public const MAX_COMPONENTS = 8192;

    public const MAX_EDGES = 131072;

    /**
     * @param  array<string, ComponentDefinition>  $definitions
     * @param  array<string, AssetPackage>  $packageBindings
     */
    public function compile(array $definitions, array $packageBindings = []): CompiledComponentGraph
    {
        if (count($definitions) > self::MAX_COMPONENTS) {
            throw new InvalidArgumentException('Component registry exceeds its 8192-node budget.');
        }
        (new ComponentOwnershipValidator)->validate($definitions);
        (new ComponentRouteCompiler)->validate($definitions);
        $order = $this->dependencyOrder($definitions);
        $projections = new ComponentProjections($definitions);
        $children = [];
        foreach ($order as $id) {
            foreach ($definitions[$id]->children as $slot) {
                $children[$id][$slot->name] = $slot->resolve($definitions[$slot->component]);
            }
        }

        return new CompiledComponentGraph($definitions, $projections,
            (new PackageAssetCompiler)->compile($definitions, $packageBindings, $projections),
            (new ComponentFamilyCompiler)->compile($definitions, $packageBindings), $children);
    }

    /**
     * Iterative DFS validates all edges, including dormant capabilities, without
     * making those optional edges part of the unconditional runtime projection.
     *
     * @param  array<string, ComponentDefinition>  $definitions
     * @return list<string>
     */
    private function dependencyOrder(array $definitions): array
    {
        $dependencies = [];
        $edgeCount = 0;
        $childCount = 0;
        $childBytes = 0;
        foreach ($definitions as $id => $definition) {
            if ($definition->childContract !== null) {
                $childBytes += strlen(json_encode($definition->childContract->export(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
                if ($childBytes > 4194304) {
                    throw new InvalidArgumentException('Compiled child configuration contracts exceed their metadata budget.');
                }
            }
            $edgeCount += count($definition->stylesFrom) + count($definition->uses) + count($definition->children);
            foreach ($definition->capabilities as $capability) {
                $edgeCount += count($capability);
            }
            if ($edgeCount > self::MAX_EDGES) {
                throw new InvalidArgumentException('Component registry exceeds its dependency-edge budget.');
            }
            $dependencies[$id] = array_values(array_unique([
                ...$definition->stylesFrom,
                ...$definition->uses,
                ...array_merge([], ...array_values($definition->capabilities)),
                ...array_map(static fn ($slot): string => $slot->component, $definition->children),
            ]));
            foreach ($dependencies[$id] as $dependency) {
                if (! isset($definitions[$dependency])) {
                    throw new InvalidArgumentException('Unknown Flex component: '.$dependency.' (required by '.$id.')');
                }
            }
            foreach ($definition->children as $slot) {
                $childBytes += strlen(json_encode($slot->export(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
                if (++$childCount > 4096 || $childBytes > 4194304) {
                    throw new InvalidArgumentException('Compiled child contracts exceed their slot or metadata budget.');
                }
                $child = $definitions[$slot->component];
                if ($child->kind !== $slot->kind || array_diff($slot->capabilities, array_keys($child->capabilities)) !== []) {
                    throw new InvalidArgumentException('Child slot kind or capabilities do not match the registered child: '.$id.' -> '.$slot->name);
                }
                if ($slot->renderer !== null && $child->surface !== null && $child->surface->class !== $slot->renderer) {
                    throw new InvalidArgumentException('Child slot renderer differs from its registered family surface.');
                }
            }
        }

        $done = [];
        $order = [];
        foreach (array_keys($definitions) as $root) {
            if (isset($done[$root])) {
                continue;
            }
            $active = [$root => true];
            $stack = [['id' => $root, 'next' => 0]];
            while ($stack !== []) {
                $last = count($stack) - 1;
                $id = $stack[$last]['id'];
                $index = $stack[$last]['next'];
                if (! isset($dependencies[$id][$index])) {
                    array_pop($stack);
                    unset($active[$id]);
                    $done[$id] = true;
                    $order[] = $id;

                    continue;
                }
                $stack[$last]['next']++;
                $dependency = $dependencies[$id][$index];
                if (isset($active[$dependency])) {
                    throw new LogicException('Circular component composition: '.implode(' -> ', [...array_keys($active), $dependency]));
                }
                if (! isset($done[$dependency])) {
                    $active[$dependency] = true;
                    $stack[] = ['id' => $dependency, 'next' => 0];
                }
            }
        }

        return $order;
    }
}
