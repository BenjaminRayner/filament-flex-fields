<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Routing;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use LogicException;

/** Catch declaration collisions before an atomic component snapshot is committed. */
final class ComponentRouteCompiler
{
    /** @param array<string, ComponentDefinition> $definitions */
    public function validate(array $definitions): void
    {
        $names = [];
        $patterns = [];
        $count = 0;
        foreach ($definitions as $component => $definition) {
            foreach ($definition->routes as $route) {
                if (++$count > 4096) {
                    throw new LogicException('Component route declarations exceed their registry budget.');
                }
                $name = $route->nameFor($component);
                if (isset($names[$name])) {
                    throw new LogicException('Duplicate component route name: '.$name);
                }
                $names[$name] = true;
                foreach ($route->methods as $method) {
                    $key = ($route->prefixConfig ?? 'filament-flex-fields.routes.prefix').' '.$method.' '.ComponentRoute::structuralPath(strtolower($route->domain ?? '*')).' '.ComponentRoute::structuralPath($route->uri);
                    if (isset($patterns[$key])) {
                        throw new LogicException('Duplicate component route URI template: '.$route->uri);
                    }
                    $patterns[$key] = true;
                }
            }
        }
    }
}
