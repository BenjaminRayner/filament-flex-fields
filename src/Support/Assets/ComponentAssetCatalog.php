<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;

/** Compatibility facade. Composition is defined exclusively in resources/components. */
final class ComponentAssetCatalog
{
    public static function registry(): ComponentRegistry
    {
        if (function_exists('app') && app()->bound(ComponentRegistry::class)) {
            return app(ComponentRegistry::class);
        }
        static $registry = null;

        if ($registry === null) {
            $registry = ComponentRegistry::builtIn();
            $registry->freeze();
        }

        return $registry;
    }

    /** @return list<string> */
    public static function stylesheetIds(): array
    {
        $ids = [];
        foreach (self::registry()->all() as $definition) {
            foreach ($definition->styles as $id) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /** @return array<string, list<string>> */
    public static function dependencies(): array
    {
        $edges = [];
        foreach (self::registry()->all() as $id => $definition) {
            $edges[$id] = array_values(array_unique([...$definition->stylesFrom, ...$definition->uses]));
        }

        return $edges;
    }

    /** @var array<string, string> */
    public const ALPINE_ENTRY_ALIASES = BuiltInComponentIndex::ALPINE_ENTRY_ALIASES;

    public const CORE_STYLESHEET_ID = 'flex-fields-core';

    public const PLAYGROUND_STYLESHEET_ID = 'flex-fields-playground';

    public const ASSET_INJECTOR_SCRIPT_ID = 'flex-field-asset-injector';

    public const FFF_LOAD_BOOTSTRAP_SCRIPT_ID = 'flex-fff-load-bootstrap';

    public const ASSET_INSPECTOR_SCRIPT_ID = 'flex-field-asset-inspector';

    public const FLEX_RICH_EDITOR_PASTE_EXTENSION_SCRIPT_ID = 'flex-rich-editor-paste-extension';

    public const FLEX_RICH_EDITOR_BLOCK_IMAGE_EXTENSION_SCRIPT_ID = 'flex-rich-editor-block-image-extension';

    public const FLEX_RICH_EDITOR_YOUTUBE_EXTENSION_SCRIPT_ID = 'flex-rich-editor-youtube-extension';

    public const SEGMENT_OVERFLOW_SSR_SCRIPT_ID = 'flex-fields-segment-overflow-ssr';

    public const TIMEZONE_BROWSER_SSR_BOOT_SCRIPT_ID = 'flex-fields-timezone-browser-ssr-boot';

    public const STATIC_ASSETS_PUBLIC_DIRECTORY = 'filament-flex-fields-assets';

    /**
     * Blocking SSR scripts emitted by shared Blade shells when segment overflow CSS loads.
     *
     * @var array<string, list<string>>
     */
    public const STYLESHEET_SSR_SCRIPTS = BuiltInComponentIndex::STYLESHEET_SSR_SCRIPTS;

    /**
     * Deprecated compatibility value. Initial SSR preloads only demanded dependencies.
     *
     * @var list<string>
     */
    public const CRITICAL_PRELOAD_STYLESHEETS = [];

    /** @var list<string> */
    public const LAZY_COMPONENT_STYLESHEETS = BuiltInComponentIndex::LAZY_COMPONENT_STYLESHEETS;

    /**
     * Declared stylesheet dependencies loaded before the component bundle.
     * Each dependency is a separate lazy CSS file — never duplicated in bundles.
     *
     * @var array<string, list<string>>
     */
    public const STYLESHEET_DEPENDENCIES = BuiltInComponentIndex::STYLESHEET_DEPENDENCIES;

    /**
     * Playground navigation slugs that differ from lazy stylesheet component ids.
     *
     * @var array<string, string>
     */
    public const PLAYGROUND_STYLESHEET_ALIASES = BuiltInComponentIndex::PLAYGROUND_STYLESHEET_ALIASES;

    /**
     * Generated compatibility projection of gallery composition dependencies.
     *
     * @var array<string, list<string>>
     */
    public const PLAYGROUND_EXTRA_STYLESHEETS = BuiltInComponentIndex::PLAYGROUND_EXTRA_STYLESHEETS;
}
