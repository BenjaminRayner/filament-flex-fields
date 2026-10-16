<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\PortableResourceCatalog;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;

/** Build-time catalog. Typed origins, portable manifests and no host URLs/source roots. */
final class PortableAssetRegistry
{
    /**
     * Format 4 bundles reference resources by identity, not ambiguous paths in
     * the core distribution. Package-relative paths live in typed descriptors.
     *
     * @return array<string, mixed>
     */
    public static function export(): array
    {
        $entries = array_fill_keys(FlexFieldAssets::alpineEntryNames(), true);
        $builtInStyles = [];
        foreach (ComponentRegistry::builtIn()->all() as $definition) {
            foreach ($definition->styles as $style) {
                $builtInStyles[$style] = true;
            }
        }
        $registry = ComponentAssetCatalog::registry();
        $assets = $registry->packageAssets();
        $resources = new PortableResourceCatalog;
        $styleReference = static function (string $id) use ($assets, $resources, $builtInStyles): string {
            if (isset($assets->styles[$id])) {
                return $resources->package($assets->styles[$id]);
            }

            return isset($builtInStyles[$id]) ? $resources->distribution('css/'.$id.'.css', 'stylesheet')
                : $resources->registered($id, 'stylesheet');
        };
        $moduleReference = static function (string $id) use ($assets, $resources, $entries): string {
            if (isset($assets->chunks[$id])) {
                return $resources->package($assets->chunks[$id]);
            }
            $entry = str_ends_with($id, '.js') ? substr($id, 0, -3) : $id;

            return isset($entries[$entry]) || in_array($id, FlexFieldAssets::alpineSharedChunkNames(), true)
                ? $resources->distribution('components/'.$id, 'module') : $resources->registered($entry, 'module');
        };
        $bundles = [];

        foreach ($registry->all() as $component => $definition) {
            $entry = $definition->entry;
            $bundles[$component] = [
                'componentId' => $component,
                'componentKind' => $definition->kind->value,
                'uses' => $definition->uses,
                'stylesFrom' => $definition->stylesFrom,
                'capabilities' => $definition->capabilities,
                'publications' => array_map(static fn ($resource): array => ['id' => $resource->id, 'publicDirectory' => $resource->publicDirectory], $definition->publications),
                'stylesheets' => array_map(
                    $styleReference,
                    FlexFieldAssets::stylesheetsFor($component),
                ),
                'chunks' => array_map(
                    $moduleReference,
                    FlexFieldAssets::alpineChunksFor($component),
                ),
                'entry' => $entry === null ? null : (isset($assets->entries[$entry])
                    ? $resources->package($assets->entries[$entry]) : $moduleReference($entry.'.js')),
                'kind' => $entry !== null ? 'full' : 'styles-only',
                'ssrScripts' => array_map(static fn (string $id): string => isset($assets->helpers[$id])
                    ? $resources->package($assets->helpers[$id]) : $resources->registered($id, 'script'), $definition->ssrScripts),
            ];
        }

        $packages = [];
        foreach ($assets->packages as $name => $package) {
            $packages[$name] = $package->manifest->export();
        }
        ksort($packages, SORT_STRING);

        return [
            'format' => 4,
            'resources' => $resources->export(),
            'packages' => $packages,
            'componentContracts' => $registry->compiledGraph()->families->export(),
            'lazy_stylesheets' => ComponentAssetCatalog::stylesheetIds(),
            'stylesheet_dependencies' => ComponentAssetCatalog::dependencies(),
            'playground_aliases' => FlexFieldAssets::PLAYGROUND_STYLESHEET_ALIASES,
            'playground_extras' => FlexFieldAssets::PLAYGROUND_EXTRA_STYLESHEETS,
            'critical_preload' => FlexFieldAssets::CRITICAL_PRELOAD_STYLESHEETS,
            'bundles' => $bundles,
        ];
    }
}
