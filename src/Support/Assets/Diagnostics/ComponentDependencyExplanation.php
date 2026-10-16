<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildSlot;

/** Installation graph only: no form values, request URLs or fabricated browser ownership. */
final class ComponentDependencyExplanation
{
    /**
     * @param  list<string>  $capabilities
     * @return array{format: int, scope: string, component: string, capabilities: list<string>, registryRevision: int, frozen: bool, roots: list<string>, runtimeComponents: list<string>, styles: array<string, string>, blockingSsrScripts: list<string>, definitions: array<string, mixed>, edges: list<array{from: string, to: string, reason: string}>, packages: array<string, mixed>, artifacts: array<string, mixed>, browserState: string}
     */
    public function explain(ComponentRegistry $registry, string $component, array $capabilities = []): array
    {
        $roots = $registry->rootsFor($component, $capabilities);
        $runtime = [];
        $styles = [];
        foreach ($roots as $root) {
            foreach ($registry->runtimeComponents($root) as $id) {
                $runtime[$id] = true;
            }
            foreach ($registry->stylesFor($root) as $style) {
                $styles[$style] = true;
            }
        }
        $queue = $roots;
        $definitions = [];
        $edges = [];
        $styleOwners = [];
        $scripts = [];
        for ($i = 0; $i < count($queue); $i++) {
            $id = $queue[$i];
            if (isset($definitions[$id])) {
                continue;
            }
            $definition = $registry->definition($id);
            foreach ($definition->ssrScripts as $script) {
                $scripts[$script] = true;
            }
            $definitions[$id] = ['kind' => $definition->kind->value, 'runtime' => isset($runtime[$id]),
                'entry' => isset($runtime[$id]) ? $definition->entry : null,
                'declaredStyles' => $definition->styles, 'declaredSsrScripts' => $definition->ssrScripts, 'surface' => $definition->surface?->export(),
                'children' => array_map(static fn (ChildSlot $slot): array => [
                    'component' => $slot->component, 'kind' => $slot->kind->value,
                    'renderer' => $slot->renderer, 'capabilities' => $slot->capabilities,
                    'shareState' => $slot->shareState, 'configurationMap' => $slot->configurationMap,
                    'configurationOptions' => $slot->configuration === null ? [] : array_keys($slot->configuration->options),
                ], $definition->children)];
            foreach ($definition->styles as $style) {
                if (isset($styles[$style])) {
                    $styleOwners[$style] = $id;
                }
            }
            foreach (['uses', 'stylesFrom'] as $type) {
                foreach ($definition->{$type} as $dependency) {
                    $edges[] = ['from' => $id, 'to' => $dependency, 'reason' => $type];
                    $queue[] = $dependency;
                }
            }
        }
        foreach ($capabilities as $capability) {
            foreach ($registry->definition($component)->capabilities[$capability] as $dependency) {
                $edges[] = ['from' => $component, 'to' => $dependency, 'reason' => 'capability:'.$capability];
            }
        }
        $packages = [];
        $artifacts = [];
        foreach (array_keys($definitions) as $id) {
            $package = $registry->packageAssets()->bindings[$id] ?? null;
            if ($package === null) {
                continue;
            }
            $manifest = $package->manifest;
            $packages[$manifest->package] = ($registry->compiledGraph()->families->packages[$manifest->package] ?? null)?->export() ?? ['release' => $manifest->release];
            $selected = [];
            if (isset($runtime[$id]) && ($entry = $registry->definition($id)->entry) !== null) {
                foreach ($manifest->eagerArtifactsFor($entry) as $artifact) {
                    $selected[$artifact] = true;
                }
            }
            foreach ($registry->definition($id)->styles as $style) {
                if (isset($styles[$style])) {
                    $selected[$manifest->styles[$style]] = true;
                }
            }
            foreach ($registry->definition($id)->ssrScripts as $script) {
                $selected[$manifest->helpers[$script]] = true;
            }
            // Lazy resources remain diagnostic entries, never eager preload requirements.
            $pending = array_keys($selected);
            $seen = [];
            for ($index = 0; $index < count($pending); $index++) {
                $artifact = $pending[$index];
                if (isset($seen[$artifact])) {
                    continue;
                }
                $seen[$artifact] = true;
                $value = $manifest->artifact($artifact);
                $key = $manifest->package.':'.$artifact;
                $eager = isset($selected[$artifact]) || ($artifacts[$key]['eager'] ?? false);
                $artifacts[$key] = [...$value->export(), 'package' => $manifest->package, 'eager' => $eager];
                foreach ([...$value->imports, ...$value->dynamicImports, ...$value->resources] as $dependency) {
                    $pending[] = $dependency;
                }
            }
        }
        ksort($definitions, SORT_STRING);
        ksort($styleOwners, SORT_STRING);
        ksort($packages, SORT_STRING);
        ksort($artifacts, SORT_STRING);

        return ['format' => 1, 'scope' => 'installation', 'component' => $component, 'capabilities' => $capabilities,
            'registryRevision' => $registry->revision(), 'frozen' => $registry->isFrozen(),
            'roots' => $roots, 'runtimeComponents' => array_keys($runtime), 'styles' => $styleOwners, 'blockingSsrScripts' => array_keys($scripts),
            'definitions' => $definitions, 'edges' => $edges, 'packages' => $packages, 'artifacts' => $artifacts,
            'browserState' => 'Inspect the live runtime diagnostics for instance owners, generations and readiness blockers.'];
    }
}
