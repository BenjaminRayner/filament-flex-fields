<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\CoreAbi;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use LogicException;

/** Request-local module identities from the compiled registry; no source paths. */
final class RenderedFactoryOwners
{
    /**
     * @param  list<string>  $components
     * @return array<string, array{package: string, component: string, export: string, abi: int}>
     */
    public static function forComponents(array $components): array
    {
        $registry = ComponentAssetCatalog::registry();
        $owners = [];
        $builtinEntries = FlexFieldAssets::alpineManifest()['__entries__'] ?? [];
        foreach ($components as $component) {
            if (! $registry->has($component)) {
                continue;
            }
            foreach ($registry->runtimeComponents($component) as $runtime) {
                $definition = $registry->definition($runtime);
                $entry = $definition->entry;
                if ($entry === null) {
                    continue;
                }
                $reference = $registry->packageAssets()->entries[$entry] ?? null;
                $owner = [
                    'package' => $reference?->package->manifest->package
                        ?? (in_array($entry, $builtinEntries, true) ? 'janczakb/filament-flex-fields' : 'legacy/registered'),
                    'component' => $entry,
                    'export' => 'default',
                    'abi' => $definition->surface?->family->package->jsAbi ?? CoreAbi::JS,
                ];
                $url = FlexFieldAssets::alpineEntrySrc($entry);
                if (isset($owners[$url]) && $owners[$url] !== $owner) {
                    throw new LogicException('Conflicting rendered factory owner for '.$entry);
                }
                $owners[$url] = $owner;
            }
        }
        if (count($owners) > 128 || strlen(json_encode($owners, JSON_THROW_ON_ERROR)) > 131072) {
            throw new LogicException('Rendered factory owner metadata exceeds its budget.');
        }

        return $owners;
    }
}
