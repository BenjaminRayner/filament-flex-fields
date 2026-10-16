<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Routing;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use LogicException;
use ReflectionClass;

/** Preflight every declaration before modifying Laravel's route collection. */
final class ComponentRouteRegistrar
{
    private bool $registered = false;

    public function register(Application $app, ComponentRegistry $registry): void
    {
        if ($this->registered || ($app instanceof \Illuminate\Foundation\Application && $app->routesAreCached())) {
            return;
        }
        if (! $registry->isFrozen()) {
            throw new LogicException('Component routes require the frozen registration snapshot.');
        }
        $router = $app->make(Router::class);
        $config = $app->make(Repository::class);
        $names = [];
        $patterns = [];
        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (($name = $route->getName()) !== null) {
                $names[$name] = 'application';
            }
            foreach ($route->methods() as $method) {
                $patterns[$this->pattern($method, $route->uri(), $route->getDomain())] = 'application';
            }
        }
        $plan = [];
        foreach ($registry->all() as $component => $definition) {
            foreach ($definition->routes as $declaration) {
                $name = $declaration->nameFor($component);
                if (isset($names[$name])) {
                    throw new LogicException('Component route name collision: '.$name.' ('.$component.' / '.$names[$name].')');
                }
                $names[$name] = $component;
                $prefix = $config->get($declaration->prefixConfig ?? 'filament-flex-fields.routes.prefix', 'flex-fields');
                if (! is_string($prefix)) {
                    throw new LogicException('Component route prefix requires text.');
                }
                $prefix = trim($prefix, '/');
                if ($prefix !== '') {
                    ComponentRoute::assertUri($prefix);
                    if (str_contains($prefix, '{')) {
                        throw new LogicException('Configured route prefixes cannot contain parameters.');
                    }
                }
                $uri = ($prefix === '' ? '' : $prefix.'/').$declaration->uri;
                $middleware = $declaration->middlewareConfig === null ? $declaration->middleware : $config->get($declaration->middlewareConfig, $declaration->middleware);
                if (! is_array($middleware)) {
                    throw new LogicException('Component route middleware configuration requires a list.');
                }
                $middleware = array_values(array_unique([...ComponentRoute::assertMiddleware($middleware, $declaration->public), ...$declaration->requiredMiddleware]));
                if (! class_exists($declaration->controller)) {
                    throw new LogicException('Missing component controller: '.$declaration->controller);
                }
                $class = new ReflectionClass($declaration->controller);
                if (! $class->isInstantiable() || ! $class->hasMethod($declaration->action) || ! $class->getMethod($declaration->action)->isPublic() || $class->getMethod($declaration->action)->isStatic()) {
                    throw new LogicException('Component routes require an instantiable controller with a public instance action.');
                }
                foreach ($declaration->methods as $method) {
                    $key = $this->pattern($method, $uri, $declaration->domain);
                    if (isset($patterns[$key])) {
                        throw new LogicException('Component route URI collision: '.$method.' '.$uri.' ('.$component.' / '.$patterns[$key].')');
                    }
                    $patterns[$key] = $component;
                }
                $plan[] = [$declaration, $name, $uri, $middleware, $component];
                if (count($plan) > 4096) {
                    throw new LogicException('Component routes exceed their registration budget.');
                }
            }
        }
        foreach ($plan as [$declaration, $name, $uri, $middleware, $component]) {
            $route = $router->match($declaration->methods, $uri, [$declaration->controller, $declaration->action])
                ->middleware($middleware)->name($name)->where($declaration->where);
            if ($declaration->domain !== null) {
                $route->domain($declaration->domain);
            }
            $route->defaults('_fff_component', $component)->defaults('_fff_endpoint', $declaration->id);
        }
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
        $this->registered = true;
    }

    private function pattern(string $method, string $uri, ?string $domain): string
    {
        return $method.' '.ComponentRoute::structuralPath(strtolower($domain ?? '*')).' '.ComponentRoute::structuralPath($uri);
    }
}
