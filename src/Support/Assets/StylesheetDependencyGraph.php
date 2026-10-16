<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

/** Deterministic dependency-first traversal shared by CSS and blocking SSR scripts. */
final class StylesheetDependencyGraph
{
    /** @return list<string> */
    public static function stylesheetsFor(string $component): array
    {
        $registry = ComponentAssetCatalog::registry();

        return $registry->has($component)
            ? StylesheetCascade::forGraph($registry->compiledGraph())->project($registry->stylesFor($component))
            : [];
    }

    /** @return list<string> */
    public static function scriptsFor(string $component): array
    {
        $registry = ComponentAssetCatalog::registry();
        if (! $registry->has($component)) {
            return [];
        }
        $scripts = [];
        $visited = [];
        $registry->validate();
        $visit = function (string $id) use (&$visit, &$scripts, &$visited, $registry): void {
            if (isset($visited[$id])) {
                return;
            }
            $visited[$id] = true;
            $definition = $registry->definition($id);
            foreach ([...$definition->stylesFrom, ...$definition->uses] as $dependency) {
                $visit($dependency);
            }
            foreach ($definition->ssrScripts as $script) {
                $scripts[$script] = true;
            }
        };
        $visit($component);

        return array_keys($scripts);
    }
}
