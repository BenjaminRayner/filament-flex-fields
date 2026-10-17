<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components;

use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildComponentContract;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildSlot;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\Components\Guidance\ComponentGuidance;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/** Immutable composition contract; stylesheet reuse never imports an unused JS entry. */
final readonly class ComponentDefinition
{
    /** @var list<string> */
    public array $styles;

    /** @var list<string> */
    public array $stylesFrom;

    /** @var list<string> */
    public array $uses;

    /** @var array<string, list<string>> */
    public array $capabilities;

    /** @var list<string> */
    public array $ssrScripts;

    /** @var list<PublishedResource> */
    public array $publications;

    /** @var array<string, ChildSlot> */
    public array $children;

    /** @var list<Routing\ComponentRoute> */
    public array $routes;

    /**
     * @param  list<Routing\ComponentRoute>  $routes
     * @param  list<string>  $styles
     * @param  list<string>  $stylesFrom
     * @param  list<string>  $uses
     * @param  array<string, list<string>>  $capabilities
     * @param  list<string>  $ssrScripts
     * @param  list<PublishedResource>  $publications
     * @param  array<string, ChildSlot>  $children
     */
    public function __construct(
        public string $id,
        public ComponentKind $kind,
        array $styles = [],
        array $stylesFrom = [],
        array $uses = [],
        public ?string $entry = null,
        array $capabilities = [],
        array $ssrScripts = [],
        array $publications = [],
        public ?ComponentPresentation $catalog = null,
        public ?ComponentSurface $surface = null,
        array $children = [],
        public ?ChildComponentContract $childContract = null,
        array $routes = [],
        public ?ComponentGuidance $guidance = null,
    ) {
        if ($guidance?->renderer !== null) {
            ComponentSurface::assertRendererClass($kind, $guidance->renderer);
            $renderer = $surface->class ?? $childContract?->renderer;
            if ($renderer !== null && $renderer !== $guidance->renderer) {
                throw new InvalidArgumentException('Guidance cannot contradict the registered component renderer.');
            }
        }
        if (! array_is_list($routes) || count($routes) > 32) {
            throw new InvalidArgumentException('Component routes require a bounded typed list.');
        }
        $routeIds = [];
        foreach ($routes as $route) {
            if (! $route instanceof Routing\ComponentRoute || isset($routeIds[$route->id])) {
                throw new InvalidArgumentException('Component route IDs must be unique typed declarations.');
            }
            $routeIds[$route->id] = true;
        }
        $this->routes = ArraySnapshot::copy($routes);
        $this->styles = ArraySnapshot::copy($styles);
        $this->stylesFrom = ArraySnapshot::copy($stylesFrom);
        $this->uses = ArraySnapshot::copy($uses);
        $this->capabilities = ArraySnapshot::copy($capabilities);
        $this->ssrScripts = ArraySnapshot::copy($ssrScripts);
        $this->publications = ArraySnapshot::copy($publications);
        $this->children = ArraySnapshot::copy($children);

        self::assertId($id);
        if ($childContract !== null && ($childContract->kind !== $kind || ($surface !== null && $surface->class !== $childContract->renderer))) {
            throw new InvalidArgumentException('Reusable child contract must match its component kind and registered renderer.');
        }
        if (count($children) > 128 || ($kind === ComponentKind::Primitive && $children !== [])) {
            throw new InvalidArgumentException('Invalid child slot budget or primitive composition.');
        }
        foreach ($children as $name => $slot) {
            if (! $slot instanceof ChildSlot || $slot->name !== $name) {
                throw new InvalidArgumentException('Children require named typed slots.');
            }
        }
        if ($surface !== null && ($surface->componentId() !== $id || $surface->kind !== $kind)) {
            throw new InvalidArgumentException('Definition must match its declared family surface identity and kind.');
        }
        if ($entry !== null) {
            self::assertId($entry);
        }
        foreach ([$styles, $stylesFrom, $uses, $ssrScripts] as $list) {
            self::assertIds($list);
        }
        if (count($capabilities) > 128) {
            throw new InvalidArgumentException('Too many component capabilities.');
        }
        foreach ($capabilities as $name => $dependencies) {
            self::assertId($name);
            self::assertIds($dependencies);
        }
        if (! array_is_list($publications) || count($publications) > 128) {
            throw new InvalidArgumentException('Invalid resource list.');
        }
        $resources = [];
        foreach ($publications as $resource) {
            if (! $resource instanceof PublishedResource || isset($resources[$resource->id])) {
                throw new InvalidArgumentException('Resources must have unique typed identities.');
            }
            $resources[$resource->id] = true;
        }
        if ($kind === ComponentKind::Primitive && $uses !== []) {
            throw new InvalidArgumentException('Primitives cannot compose interactive components.');
        }
    }

    private static function assertId(mixed $id): void
    {
        if (! is_string($id) || ! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $id) || strlen($id) > 96) {
            throw new InvalidArgumentException('Component identities must be bounded lowercase kebab-case strings.');
        }
    }

    /** @param array<array-key, mixed> $ids */
    private static function assertIds(array $ids): void
    {
        foreach ($ids as $id) {
            self::assertId($id);
        }
        if (! array_is_list($ids) || count($ids) > 128 || count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
            throw new InvalidArgumentException('Dependencies must be a bounded list of unique identities.');
        }
    }
}
