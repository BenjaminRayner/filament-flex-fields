<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Routing;

use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/** Server-only controller declaration; closures and browser-supplied handlers are forbidden. */
final readonly class ComponentRoute
{
    /** @var list<string> */
    public array $methods;

    /** @var list<string> */
    public array $middleware;

    /** @var list<string> */
    public array $requiredMiddleware;

    /** @var array<string, string> */
    public array $where;

    /** @param class-string $controller
     * @param  list<string>  $methods
     * @param  list<string>  $middleware
     * @param  list<string>  $requiredMiddleware
     * @param  array<string, string>  $where
     */
    public function __construct(
        public string $id,
        public string $uri,
        public string $controller,
        public string $action = '__invoke',
        array $methods = ['POST'],
        array $middleware = ['web', 'auth'],
        public ?string $name = null,
        public ?string $prefixConfig = null,
        public ?string $middlewareConfig = null,
        public bool $public = false,
        public ?string $domain = null,
        array $where = [],
        array $requiredMiddleware = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9-]{0,95}$/D', $id)) {
            throw new InvalidArgumentException('Component routes require bounded kebab-case IDs.');
        }
        self::assertUri($uri);
        if ($name !== null && (! preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,191}$/D', $name) || ! str_contains($name, '.'))) {
            throw new InvalidArgumentException('Explicit component route names must be namespaced.');
        }
        foreach ([$prefixConfig, $middlewareConfig] as $key) {
            if ($key !== null && ! preg_match('/^[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)+$/D', $key)) {
                throw new InvalidArgumentException('Route configuration references require named config keys.');
            }
        }
        if (! array_is_list($methods) || $methods === [] || count($methods) > 8 || count(array_filter($methods, 'is_string')) !== count($methods) || count(array_unique($methods)) !== count($methods) || array_diff($methods, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'QUERY']) !== []) {
            throw new InvalidArgumentException('Invalid component route HTTP methods.');
        }
        self::assertMiddleware($middleware, $public);
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $action) || (str_starts_with($action, '__') && $action !== '__invoke')) {
            throw new InvalidArgumentException('Invalid controller action.');
        }
        if ($domain !== null && (strlen($domain) > 253 || ! preg_match('/^(?:[a-zA-Z0-9_-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\})(?:\.(?:[a-zA-Z0-9_-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\}))*$/D', $domain))) {
            throw new InvalidArgumentException('Invalid component route domain.');
        }
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\??\}/', $uri.' '.($domain ?? ''), $matches);
        $parameters = $matches[1];
        if (count($parameters) !== count(array_unique($parameters))) {
            throw new InvalidArgumentException('Route parameter names must be unique.');
        }
        if (count($where) > 16) {
            throw new InvalidArgumentException('Too many route constraints.');
        }
        foreach ($where as $parameter => $pattern) {
            if (! is_string($parameter) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $parameter) || ! is_string($pattern) || strlen($pattern) > 1024 || str_contains($pattern, "\0")) {
                throw new InvalidArgumentException('Route constraints require bounded named patterns.');
            }
            if (! in_array($parameter, $parameters, true)) {
                throw new InvalidArgumentException('Route constraints must reference declared parameters.');
            }
            if (@preg_match('~(?:'.str_replace('~', '\\~', $pattern).')~', '') === false) {
                throw new InvalidArgumentException('Invalid component route regular expression.');
            }
        }
        $this->methods = ArraySnapshot::copy(in_array('GET', $methods, true) && ! in_array('HEAD', $methods, true) ? [...$methods, 'HEAD'] : $methods);
        $this->middleware = ArraySnapshot::copy($middleware);
        $this->where = ArraySnapshot::copy($where);
        $this->requiredMiddleware = ArraySnapshot::copy(self::assertMiddleware($requiredMiddleware, true));
    }

    public function nameFor(string $component): string
    {
        return $this->name ?? 'filament-flex-fields.components.'.$component.'.'.$this->id;
    }

    public static function structuralPath(string $template): string
    {
        return preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*(\??)\}/', '{$1}', trim($template, '/')) ?? $template;
    }

    public static function assertUri(string $uri): void
    {
        if ($uri === '' || strlen($uri) > 1024 || ! preg_match('~^(?:[a-zA-Z0-9_.-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\??\})(?:/(?:[a-zA-Z0-9_.-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\??\}))*$~D', $uri) || array_intersect(explode('/', $uri), ['.', '..']) !== []) {
            throw new InvalidArgumentException('Component route paths must be safe relative URI templates.');
        }
    }

    /** @param array<array-key, mixed> $middleware
     * @return list<string>
     */
    public static function assertMiddleware(array $middleware, bool $public): array
    {
        if (! array_is_list($middleware) || count($middleware) > 32) {
            throw new InvalidArgumentException('Invalid component middleware list.');
        }
        $authenticated = false;
        $validated = [];
        foreach ($middleware as $item) {
            if (! is_string($item) || $item === '' || strlen($item) > 512 || preg_match('/[\x00-\x1f\x7f]/', $item)) {
                throw new InvalidArgumentException('Middleware must be bounded names.');
            }
            $validated[] = $item;
            $authenticated = $authenticated || $item === 'auth' || str_starts_with($item, 'auth:');
        }
        if (count(array_unique($validated)) !== count($validated)) {
            throw new InvalidArgumentException('Duplicate middleware declarations.');
        }
        if (! $public && ! $authenticated) {
            throw new InvalidArgumentException('Authenticated component routes require auth middleware; public access must be explicit.');
        }

        return $validated;
    }
}
