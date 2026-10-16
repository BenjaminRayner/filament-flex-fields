<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaDefinition;
use InvalidArgumentException;

/** Read-only preflight. Includes dormant edges; never removes packages, releases or values. */
final class ComponentRemovalImpact
{
    /**
     * @param  list<FamilySchemaDefinition>  $schemas  The application's explicit stored-schema inventory.
     * @return array<string, mixed>
     */
    public function inspect(ComponentRegistry $registry, string $package, array $schemas = []): array
    {
        $graph = $registry->compiledGraph();
        if (! isset($graph->packageAssets->packages[$package])) {
            throw new InvalidArgumentException('Removal preflight requires an installed asset package identity.');
        }
        if (! array_is_list($schemas) || count($schemas) > 256) {
            throw new InvalidArgumentException('Removal preflight requires at most 256 typed schema definitions per batch.');
        }
        $definitions = $graph->definitions();
        if (count($definitions) > 8192) {
            throw new InvalidArgumentException('Removal preflight exceeds its definition budget.');
        }
        $owned = [];
        foreach ($graph->packageAssets->bindings as $id => $binding) {
            if ($binding->manifest->package === $package) {
                $owned[$id] = true;
            }
        }
        $reverse = [];
        $edges = [];
        foreach ($definitions as $id => $definition) {
            $dependencies = ['uses' => $definition->uses, 'stylesFrom' => $definition->stylesFrom];
            foreach ($definition->capabilities as $name => $components) {
                $dependencies['capability:'.$name] = $components;
            }
            foreach ($definition->children as $name => $slot) {
                $dependencies['child:'.$name] = [$slot->component];
            }
            foreach ($dependencies as $reason => $components) {
                foreach ($components as $dependency) {
                    if (count($edges) >= 65536) {
                        throw new InvalidArgumentException('Removal preflight exceeds its dependency edge budget.');
                    }
                    $edges[] = ['from' => $id, 'to' => $dependency, 'reason' => $reason];
                    $reverse[$dependency][$id] = true;
                }
            }
        }
        $affected = $owned;
        $queue = array_keys($owned);
        for ($index = 0; $index < count($queue); $index++) {
            foreach ($reverse[$queue[$index]] ?? [] as $id => $_) {
                if (! isset($affected[$id])) {
                    $affected[$id] = true;
                    $queue[] = $id;
                }
            }
        }
        $families = [];
        foreach ($graph->families->families as $id => $family) {
            foreach ($family->surfaces as $component) {
                if (isset($affected[$component])) {
                    $families[$id] = true;
                    break;
                }
            }
        }
        $stored = [];
        $seen = [];
        foreach ($schemas as $schema) {
            if (! $schema instanceof FamilySchemaDefinition || isset($seen[$schema->slug])) {
                throw new InvalidArgumentException('Removal schema inventory requires typed definitions with unique slugs.');
            }
            $seen[$schema->slug] = true;
            if (isset($families[$schema->family])) {
                $stored[] = ['family' => $schema->family, 'slug' => $schema->slug,
                    'configurationVersion' => $schema->configurationVersion, 'valueVersion' => $schema->valueVersion];
            }
        }
        $dependents = array_diff_key($affected, $owned);
        $blockedEdges = array_values(array_filter($edges, static fn (array $edge): bool => isset($dependents[$edge['from']], $affected[$edge['to']])));
        usort($blockedEdges, static fn (array $a, array $b): int => [$a['from'], $a['to'], $a['reason']] <=> [$b['from'], $b['to'], $b['reason']]);
        usort($stored, static fn (array $a, array $b): int => $a['slug'] <=> $b['slug']);
        foreach ([$owned, $dependents, $families] as $index => $set) {
            $ids[$index] = array_keys($set);
            sort($ids[$index], SORT_STRING);
        }

        return ['format' => 1, 'scope' => 'installation-and-supplied-schemas', 'package' => $package,
            'registryRevision' => $registry->revision(), 'ownedComponents' => $ids[0],
            'dependentComponents' => $ids[1], 'dependencyEdges' => $blockedEdges,
            'affectedFamilies' => $ids[2], 'affectedSchemas' => $stored, 'inspectedSchemas' => count($schemas),
            'readyWithinSuppliedScope' => $dependents === [] && $stored === [],
            'limitation' => 'The application must inventory every persisted schema and record before removal. This report does not scan databases or revoke cached browser modules.'];
    }
}
