<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Filament\Schemas\Components\Component;
use WeakMap;

/** Recognize installed surface metadata without constructing renderers or dormant schemas. */
final class SchemaRendererIdentity
{
    /** @var WeakMap<ComponentRegistry, array{revision: int, classes: list<class-string>, matches: array<class-string, bool>}>|null */
    private static ?WeakMap $indexes = null;

    public static function isFlex(Component $component): bool
    {
        if (($component->hasView() && str_starts_with($component->getView(), 'filament-flex-fields::'))
            || isset($component->getExtraAttributes()['data-fff-component'])) {
            return true;
        }

        $registry = app(ComponentRegistry::class);
        self::$indexes ??= new WeakMap;
        $index = self::$indexes[$registry] ?? null;
        if ($index === null || $index['revision'] !== $registry->revision()) {
            $classes = [];
            foreach ($registry->compiledGraph()->families->surfaces as $surface) {
                if (! str_starts_with($surface->class, 'Filament\\') && is_a($surface->class, Component::class, true)) {
                    $classes[] = $surface->class;
                }
            }
            $index = ['revision' => $registry->revision(), 'classes' => array_values(array_unique($classes)), 'matches' => []];
        }

        $class = $component::class;
        if (! array_key_exists($class, $index['matches'])) {
            $match = false;
            foreach ($index['classes'] as $renderer) {
                if (is_a($component, $renderer)) {
                    $match = true;
                    break;
                }
            }
            if (count($index['matches']) >= 256) {
                $index['matches'] = [];
            }
            $index['matches'][$class] = $match;
        }
        self::$indexes[$registry] = $index;

        return $index['matches'][$class];
    }
}
