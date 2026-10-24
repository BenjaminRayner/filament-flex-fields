<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use WeakMap;

/** Per-instance requirements. Optional capabilities never expand the whole type's bundle. */
final readonly class ComponentAssetPlan
{
    /**
     * @param  list<string>  $components
     * @param  list<string>  $stylesheets
     * @param  list<string>  $chunks
     * @param  list<string>  $queuedStylesheets
     */
    private function __construct(
        public string $component,
        public array $components,
        public array $stylesheets,
        public array $chunks,
        public array $queuedStylesheets,
    ) {}

    /** @param list<string> $capabilities */
    public static function for(string $component, array $capabilities = []): self
    {
        $component = FlexFieldAssets::resolveCanonicalComponent($component);
        $registry = ComponentAssetCatalog::registry();
        // Validate even warm requests; never turn an invalid capability into a cache hit.
        $components = $registry->rootsFor($component, $capabilities);
        $registry->validate();
        /** @var WeakMap<ComponentRegistry, array{revision: int, plans: array<string, self>}>|null $cache */
        static $cache = null;
        $cache ??= new WeakMap;
        $partition = $cache[$registry] ?? ['revision' => $registry->revision(), 'plans' => []];
        if ($partition['revision'] !== $registry->revision()) {
            $partition = ['revision' => $registry->revision(), 'plans' => []];
        }
        // Preserve dependency order; caller ordering can affect the emitted plan.
        $key = implode('|', $components);
        if (isset($partition['plans'][$key])) {
            return $partition['plans'][$key];
        }

        $stylesheets = [];
        $queuedStylesheets = [];
        $chunks = [];

        foreach ($components as $root) {
            $rootStylesheets = FlexFieldAssets::stylesheetsFor($root);
            $stylesheets = [...$stylesheets, ...$rootStylesheets];
            $queueRoot = FlexFieldAssets::resolveStylesheetComponent($root);
            $queuedStylesheets = [...$queuedStylesheets, ...($queueRoot === $root
                ? $rootStylesheets : FlexFieldAssets::stylesheetsFor($queueRoot))];
            $chunks = [...$chunks, ...FlexFieldAssets::alpineChunksFor($root)];
        }

        $plan = new self($component, $components, StylesheetCascade::forGraph($registry->compiledGraph())->project($stylesheets),
            array_values(array_unique($chunks)), array_values(array_unique($queuedStylesheets)));
        if (count($partition['plans']) >= 256) {
            unset($partition['plans'][array_key_first($partition['plans'])]);
        }
        $partition['plans'][$key] = $plan;
        $cache[$registry] = $partition;

        return $plan;
    }
}
