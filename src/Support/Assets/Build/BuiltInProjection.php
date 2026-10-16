<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Build;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;

/** One projection used by generation and independent deployment validation. */
final class BuiltInProjection
{
    /** @return array<string, array<array-key, mixed>> */
    public static function compile(): array
    {
        $styles = [];
        $dependencies = [];
        $aliases = [];
        $galleryAliases = [];
        $galleryExtras = [];
        $ssr = [];
        foreach (ComponentRegistry::builtIn()->all() as $id => $definition) {
            foreach ($definition->styles as $style) {
                $styles[$style] = true;
            }
            $dependencies[$id] = array_values(array_unique([...$definition->stylesFrom, ...$definition->uses]));
            if ($definition->ssrScripts !== []) {
                $ssr[$id] = $definition->ssrScripts;
            }
            if ($definition->catalog !== null && $definition->styles === [] && $definition->uses !== []) {
                $galleryAliases[$id] = $definition->uses[0];
                if (count($definition->uses) > 1) {
                    $galleryExtras[$id] = array_slice($definition->uses, 1);
                }
            }
            if ($definition->entry !== null && $definition->entry !== $id) {
                $aliases[$id] = $definition->entry;
            }
        }

        return ['LAZY_COMPONENT_STYLESHEETS' => array_keys($styles), 'STYLESHEET_DEPENDENCIES' => $dependencies,
            'ALPINE_ENTRY_ALIASES' => $aliases, 'PLAYGROUND_STYLESHEET_ALIASES' => $galleryAliases,
            'PLAYGROUND_EXTRA_STYLESHEETS' => $galleryExtras, 'STYLESHEET_SSR_SCRIPTS' => $ssr];
    }
}
