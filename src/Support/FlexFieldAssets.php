<?php

declare(strict_types=1);

/**
 * @author Bartłomiej Janczak <barek122@gmail.com>
 * @copyright Copyright (c) 2026 Bartłomiej Janczak. All rights reserved.
 * @license Proprietary
 */

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundCluster;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\AlpineBuildManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\DevelopmentAssetFallback;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\PackageArtifactReference;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\FirstPaintScripts;
use Bjanczak\FilamentFlexFields\Support\Assets\StylesheetDependencyGraph;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Filesystem\Filesystem;
use Livewire\LivewireManager;

class FlexFieldAssets
{
    public const CORE_STYLESHEET_ID = ComponentAssetCatalog::CORE_STYLESHEET_ID;

    public const PLAYGROUND_STYLESHEET_ID = ComponentAssetCatalog::PLAYGROUND_STYLESHEET_ID;

    public const ASSET_INJECTOR_SCRIPT_ID = ComponentAssetCatalog::ASSET_INJECTOR_SCRIPT_ID;

    public const FFF_LOAD_BOOTSTRAP_SCRIPT_ID = ComponentAssetCatalog::FFF_LOAD_BOOTSTRAP_SCRIPT_ID;

    public const ASSET_INSPECTOR_SCRIPT_ID = ComponentAssetCatalog::ASSET_INSPECTOR_SCRIPT_ID;

    public const FLEX_RICH_EDITOR_PASTE_EXTENSION_SCRIPT_ID = ComponentAssetCatalog::FLEX_RICH_EDITOR_PASTE_EXTENSION_SCRIPT_ID;

    public const FLEX_RICH_EDITOR_BLOCK_IMAGE_EXTENSION_SCRIPT_ID = ComponentAssetCatalog::FLEX_RICH_EDITOR_BLOCK_IMAGE_EXTENSION_SCRIPT_ID;

    public const FLEX_RICH_EDITOR_YOUTUBE_EXTENSION_SCRIPT_ID = ComponentAssetCatalog::FLEX_RICH_EDITOR_YOUTUBE_EXTENSION_SCRIPT_ID;

    public const SEGMENT_OVERFLOW_SSR_SCRIPT_ID = ComponentAssetCatalog::SEGMENT_OVERFLOW_SSR_SCRIPT_ID;

    public const TIMEZONE_BROWSER_SSR_BOOT_SCRIPT_ID = ComponentAssetCatalog::TIMEZONE_BROWSER_SSR_BOOT_SCRIPT_ID;

    public const STATIC_ASSETS_PUBLIC_DIRECTORY = ComponentAssetCatalog::STATIC_ASSETS_PUBLIC_DIRECTORY;

    public const STYLESHEET_SSR_SCRIPTS = ComponentAssetCatalog::STYLESHEET_SSR_SCRIPTS;

    public const CRITICAL_PRELOAD_STYLESHEETS = ComponentAssetCatalog::CRITICAL_PRELOAD_STYLESHEETS;

    public const LAZY_COMPONENT_STYLESHEETS = ComponentAssetCatalog::LAZY_COMPONENT_STYLESHEETS;

    public static function stylesheetId(string $component): string
    {
        return 'flex-fields-'.$component;
    }

    public static function hasLazyStylesheet(string $component): bool
    {
        return in_array($component, ComponentAssetCatalog::stylesheetIds(), true);
    }

    public static function shouldLoadStylesheetsFor(string $component): bool
    {
        $component = self::resolveStylesheetComponent($component);

        return self::hasLazyStylesheet($component)
            || ComponentAssetCatalog::registry()->has($component);
    }

    public static function resolveStylesheetComponent(string $component): string
    {
        return self::PLAYGROUND_STYLESHEET_ALIASES[$component] ?? $component;
    }

    public const STYLESHEET_DEPENDENCIES = ComponentAssetCatalog::STYLESHEET_DEPENDENCIES;

    public const PLAYGROUND_STYLESHEET_ALIASES = ComponentAssetCatalog::PLAYGROUND_STYLESHEET_ALIASES;

    public const PLAYGROUND_EXTRA_STYLESHEETS = ComponentAssetCatalog::PLAYGROUND_EXTRA_STYLESHEETS;

    /**
     * @return list<string>
     */
    public static function stylesheetsFor(string $component): array
    {
        return StylesheetDependencyGraph::stylesheetsFor(self::resolveCanonicalComponent($component));
    }

    /**
     * Blocking SSR scripts required by shared Blade shells for a component.
     *
     * @return list<string>
     */
    public static function ssrScriptsFor(string $component): array
    {
        return StylesheetDependencyGraph::scriptsFor(self::resolveCanonicalComponent($component));
    }

    /**
     * @return list<string>
     */
    public static function stylesheetHrefsFor(string $component): array
    {
        return array_map(
            fn (string $stylesheet): string => self::stylesheetHref($stylesheet),
            self::stylesheetsFor($component),
        );
    }

    /**
     * Deduped lazy CSS + Alpine chunks for a set of component ids, preserving
     * dependency order from stylesheetsFor() / alpineChunksFor().
     *
     * @param  list<string>  $components
     * @return array{stylesheets: list<string>, chunks: list<string>}
     */
    public static function planAssetsForComponents(array $components): array
    {
        $stylesheets = [];
        $chunks = [];

        foreach (array_values(array_unique($components)) as $component) {
            if (! is_string($component) || $component === '') {
                continue;
            }

            foreach (self::stylesheetsFor($component) as $stylesheet) {
                $stylesheets[$stylesheet] = true;
            }

            foreach (self::alpineChunksFor($component) as $chunk) {
                $chunks[$chunk] = true;
            }
        }

        return [
            'stylesheets' => array_keys($stylesheets),
            'chunks' => array_keys($chunks),
        ];
    }

    /**
     * @return list<string>
     */
    public static function playgroundStylesheetsFor(string $slug): array
    {
        return self::stylesheetsFor($slug);
    }

    public static function stylesheetHref(string $component): string
    {
        $reference = ComponentAssetCatalog::registry()->packageAssets()->styles[$component] ?? null;
        if ($reference !== null) {
            return self::packageArtifactUrl($reference);
        }

        return FilamentAsset::getStyleHref(
            self::stylesheetId($component),
            FilamentFlexFieldsPlugin::PACKAGE_NAME,
        );
    }

    /**
     * Minified blocking IIFE inlined next to each overflow shell (no network round-trip).
     */
    public static function segmentOverflowSsrInlineContents(): string
    {
        return RequestState::resolve(FirstPaintScripts::class)->inline('segment');
    }

    /**
     * Minified blocking IIFE inlined next to browserTimezoneDefault triggers
     * (paints Intl timezone before Alpine x-load — no network round-trip).
     */
    public static function timezoneBrowserSsrInlineContents(): string
    {
        return RequestState::resolve(FirstPaintScripts::class)->inline('timezone');
    }

    public static function playgroundStylesheetHref(): string
    {
        return FilamentAsset::getStyleHref(
            self::PLAYGROUND_STYLESHEET_ID,
            FilamentFlexFieldsPlugin::PACKAGE_NAME,
        );
    }

    public static function resolvePlaygroundSlugFromRequest(): ?string
    {
        $catalogSlug = preg_quote(FlexFieldsPlaygroundCluster::SLUG, '#');

        if (preg_match('#(?:^|/)'.$catalogSlug.'/([^/]+)(?:/|$)#', request()->path(), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public static function isPlaygroundRequest(): bool
    {
        $slug = FlexFieldsPlaygroundCluster::SLUG;

        return request()->is($slug, $slug.'/*', '*/'.$slug, '*/'.$slug.'/*');
    }

    /**
     * @return list<string>
     */
    public static function playgroundStylesheetHrefsForSlug(?string $slug): array
    {
        if (blank($slug)) {
            return [self::playgroundStylesheetHref()];
        }

        // Component styles have one URL/owner across the page and its modals.
        // Concatenated playground copies cannot participate in reference counting.
        return array_values(array_unique([
            self::playgroundStylesheetHref(),
            ...array_map(self::stylesheetHref(...), self::playgroundStylesheetsFor($slug)),
        ]));
    }

    public static function playgroundStylesheetHrefForSlug(?string $slug): string
    {
        $hrefs = self::playgroundStylesheetHrefsForSlug($slug);
        $last = array_key_last($hrefs);

        if ($last === null) {
            return self::playgroundStylesheetHref();
        }

        return $hrefs[$last];
    }

    /**
     * @return list<string>
     */
    public static function playgroundStylesheetHrefsForRequest(): array
    {
        if (! self::isPlaygroundRequest()) {
            return [];
        }

        $slug = self::resolvePlaygroundSlugFromRequest();

        return self::playgroundStylesheetHrefsForSlug($slug);
    }

    public static function playgroundStylesheetHrefForRequest(): ?string
    {
        $hrefs = self::playgroundStylesheetHrefsForRequest();

        if ($hrefs === []) {
            return null;
        }

        return $hrefs[array_key_last($hrefs)];
    }

    public static function resolveCanonicalComponent(string $component): string
    {
        return ComponentAssetCatalog::registry()->has($component) ? $component : self::resolveStylesheetComponent($component);
    }

    /**
     * @return array{
     *     componentId: string,
     *     stylesheets: list<string>,
     *     chunks: list<string>,
     *     entry: string|null,
     *     kind: 'full'|'styles-only',
     * }
     */
    public static function assetBundleFor(string $component): array
    {
        $canonical = self::resolveCanonicalComponent($component);
        $entry = self::resolveAlpineEntryComponent($canonical);
        $manifest = self::alpineManifest();
        $hasAlpineEntry = ComponentAssetCatalog::registry()->has($canonical)
            && ComponentAssetCatalog::registry()->definition($canonical)->entry !== null;

        return [
            'componentId' => $canonical,
            'componentKind' => ComponentAssetCatalog::registry()->definition($canonical)->kind->value,
            'stylesheets' => self::stylesheetHrefsFor($canonical),
            'chunks' => array_map(
                fn (string $chunk): string => self::alpineChunkSrc($chunk),
                self::alpineChunksFor($canonical),
            ),
            'entry' => $hasAlpineEntry ? self::alpineEntrySrc($entry) : null,
            'kind' => $hasAlpineEntry ? 'full' : 'styles-only',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function consumerAttributesFor(string $livewireKey, string $component): array
    {
        return [
            'data-fff-asset-consumer' => self::resolveCanonicalComponent($component),
            'data-fff-asset-consumer-id' => $livewireKey,
        ];
    }

    /**
     * Stable CRG consumer-id for emit-assets. Never returns blank — keyless schema
     * components (ItemCardStack::make()) must still retain lazy CSS after injector boot.
     */
    public static function resolveAssetConsumerLivewireKey(string $component, ?string $livewireKey = null): string
    {
        if (filled($livewireKey)) {
            return $livewireKey;
        }

        $component = self::resolveCanonicalComponent($component);
        $livewire = app(LivewireManager::class)->current();
        $livewireId = is_object($livewire) && method_exists($livewire, 'getId')
            ? $livewire->getId()
            : null;

        if (filled($livewireId)) {
            return $livewireId.'.'.$component;
        }

        return 'page.'.$component;
    }

    /**
     * @return array<string, string>
     */
    public static function consumerAttributesForComponent(string $component, ?string $livewireKey = null): array
    {
        $canonical = self::resolveCanonicalComponent($component);

        return self::consumerAttributesFor(
            self::resolveAssetConsumerLivewireKey($canonical, $livewireKey),
            $canonical,
        );
    }

    /**
     * @return array<string, array{
     *     componentId: string,
     *     stylesheets: list<string>,
     *     chunks: list<string>,
     *     entry: string|null,
     *     kind: string,
     * }>
     */
    public static function exportConsumerBundles(): array
    {
        $bundles = [];

        foreach (array_keys(ComponentAssetCatalog::registry()->all()) as $component) {
            $bundles[$component] = self::assetBundleFor($component);
        }

        return $bundles;
    }

    /**
     * @return array{
     *     lazy_stylesheets: list<string>,
     *     stylesheet_dependencies: array<string, list<string>>,
     *     playground_aliases: array<string, string>,
     *     playground_extras: array<string, list<string>>,
     *     critical_preload: list<string>,
     *     bundles: array<string, array{
     *         componentId: string,
     *         stylesheets: list<string>,
     *         chunks: list<string>,
     *         entry: string|null,
     *         kind: string,
     *     }>,
     * }
     */
    public static function exportRegistry(): array
    {
        return [
            'lazy_stylesheets' => ComponentAssetCatalog::stylesheetIds(),
            'stylesheet_dependencies' => ComponentAssetCatalog::dependencies(),
            'playground_aliases' => self::PLAYGROUND_STYLESHEET_ALIASES,
            'playground_extras' => self::PLAYGROUND_EXTRA_STYLESHEETS,
            'critical_preload' => self::CRITICAL_PRELOAD_STYLESHEETS,
            'bundles' => self::exportConsumerBundles(),
        ];
    }

    public static function alpineManifestPath(): string
    {
        return __DIR__.'/../../resources/dist/components/alpine-manifest.json';
    }

    /**
     * @return array<string, mixed>
     */
    public static function alpineManifest(): array
    {
        try {
            return RequestState::resolve(AlpineBuildManifest::class)->data();
        } catch (\RuntimeException $exception) {
            DevelopmentAssetFallback::report($exception);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    public static function alpineEntryNames(): array
    {
        return array_values(array_filter(
            array_keys(self::alpineManifest()),
            fn (string $key): bool => ! str_starts_with($key, '__'),
        ));
    }

    /**
     * @return list<string>
     */
    public static function alpineSharedChunkNames(): array
    {
        $chunks = self::alpineManifest()['__shared_chunks__'] ?? [];

        if (! is_array($chunks)) {
            return [];
        }

        return array_values(array_filter($chunks, fn (mixed $chunk): bool => is_string($chunk) && $chunk !== ''));
    }

    /**
     * @return list<string>
     */
    public static function alpineChunksFor(string $component): array
    {
        $chunks = [];
        if (! ComponentAssetCatalog::registry()->has(self::resolveCanonicalComponent($component))) {
            return [];
        }
        foreach (ComponentAssetCatalog::registry()->runtimeComponents(self::resolveCanonicalComponent($component)) as $id) {
            $packageAssets = ComponentAssetCatalog::registry()->packageAssets();
            if (isset($packageAssets->bindings[$id])) {
                $chunks = [...$chunks, ...$packageAssets->eagerChunks[$id]];

                continue;
            }
            $entry = ComponentAssetCatalog::registry()->definition($id)->entry;
            if ($entry !== null) {
                $chunks = [...$chunks, $entry.'.js', ...self::alpineChunksForEntry($entry)];
            }
        }

        return array_values(array_unique($chunks));
    }

    /** @return list<string> */
    private static function alpineChunksForEntry(string $component): array
    {
        $component = self::resolveAlpineEntryComponent($component);
        $manifest = self::alpineManifest();

        $chunks = $manifest[$component] ?? [];

        if (! is_array($chunks)) {
            $chunks = [];
        }

        if (($manifest['__format__'] ?? 1) < 2 && $component === 'select-field') {
            $overlayCoordinatorChunk = self::overlayCoordinatorChunk($manifest);

            if (is_string($overlayCoordinatorChunk) && $overlayCoordinatorChunk !== '') {
                $chunks[] = $overlayCoordinatorChunk;
            }
        }

        $chunks = self::expandTransitiveAlpineChunks($chunks, $manifest);

        return array_values(array_filter(
            array_unique($chunks),
            fn (mixed $chunk): bool => is_string($chunk)
                && $chunk !== ''
                && (($manifest['__format__'] ?? 1) >= 2 || ! str_starts_with($chunk, 'flex-fields-phone-lib')),
        ));
    }

    /**
     * Resolve eager transitive edges. Format 2 keeps dynamic imports out of preloads.
     *
     * @param  list<string>  $chunks
     * @return list<string>
     */
    public static function expandTransitiveAlpineChunks(array $chunks, ?array $manifest = null): array
    {
        $manifest ??= self::alpineManifest();
        $graph = $manifest['__chunk_imports__'] ?? [];

        if (! is_array($graph) || $graph === []) {
            return array_values(array_unique($chunks));
        }

        $expanded = [];
        $seen = [];
        $queue = array_values(array_unique(array_filter(
            $chunks,
            fn (mixed $chunk): bool => is_string($chunk) && $chunk !== '',
        )));

        while ($queue !== []) {
            $chunk = array_shift($queue);

            if (! is_string($chunk) || $chunk === '' || isset($seen[$chunk])) {
                continue;
            }

            $seen[$chunk] = true;
            $expanded[] = $chunk;

            foreach ($graph[$chunk] ?? [] as $nestedChunk) {
                if (is_string($nestedChunk) && $nestedChunk !== '' && ! isset($seen[$nestedChunk])) {
                    $queue[] = $nestedChunk;
                }
            }
        }

        return $expanded;
    }

    public static function overlayCoordinatorChunk(?array $manifest = null): ?string
    {
        $manifest ??= self::alpineManifest();

        foreach ($manifest['__chunk_modules__'] ?? [] as $chunk => $modules) {
            if (! is_string($chunk) || ! is_array($modules)) {
                continue;
            }

            if (in_array('core/flex-dropdown-coordinator.js', $modules, true)) {
                return $chunk;
            }
        }

        return null;
    }

    public static function alpineChunkSrc(string $chunk): string
    {
        $reference = ComponentAssetCatalog::registry()->packageAssets()->chunks[$chunk] ?? null;
        if ($reference !== null) {
            return self::packageArtifactUrl($reference);
        }

        return FilamentAsset::getAlpineComponentSrc(
            str_replace('.js', '', $chunk),
            FilamentFlexFieldsPlugin::PACKAGE_NAME,
        );
    }

    public static function resolveAlpineEntryComponent(string $component): string
    {
        if (ComponentAssetCatalog::registry()->has($component)) {
            return ComponentAssetCatalog::registry()->definition($component)->entry ?? $component;
        }

        return ComponentAssetCatalog::ALPINE_ENTRY_ALIASES[$component] ?? $component;
    }

    public static function alpineEntrySrc(string $component): string
    {
        $reference = ComponentAssetCatalog::registry()->packageAssets()->entries[$component] ?? null;
        if ($reference !== null) {
            return self::packageArtifactUrl($reference);
        }

        return FilamentAsset::getAlpineComponentSrc(
            $component,
            FilamentFlexFieldsPlugin::PACKAGE_NAME,
        );
    }

    public static function ssrScriptSrc(string $helper): string
    {
        $reference = ComponentAssetCatalog::registry()->packageAssets()->helpers[$helper] ?? null;

        return $reference !== null ? self::packageArtifactUrl($reference)
            : FilamentAsset::getScriptSrc($helper, FilamentFlexFieldsPlugin::PACKAGE_NAME);
    }

    public static function packageArtifactUrl(PackageArtifactReference $reference): string
    {
        $prefix = config('filament.assets_path') ?? '';
        if (! is_string($prefix)) {
            throw new \InvalidArgumentException('Filament asset prefix must be a string.');
        }

        return asset($reference->relativePublicPath($prefix));
    }

    public static function stylesheetBytes(string $style): ?int
    {
        return ComponentAssetCatalog::registry()->packageAssets()->styles[$style]->artifact->bytes
            ?? app(AssetVersionManifest::class)->bytesFor('css/'.$style.'.css');
    }

    public static function chunkBytes(string $chunk): ?int
    {
        return ComponentAssetCatalog::registry()->packageAssets()->chunks[$chunk]->artifact->bytes
            ?? app(AssetVersionManifest::class)->bytesFor('components/'.$chunk);
    }

    public static function whisperRuntimeModuleSrc(): string
    {
        return self::assetUrl('whisper/'.WhisperRuntimeManifest::VERSION.'/transformers.min.js');
    }

    public static function whisperRuntimeWasmBaseSrc(): string
    {
        $moduleSrc = self::whisperRuntimeModuleSrc();
        $base = preg_replace('#/[^/]+$#', '/', $moduleSrc) ?? $moduleSrc;

        return str_ends_with($base, '/') ? $base : "{$base}/";
    }

    public static function assetUrl(string $path): string
    {
        $relative = self::STATIC_ASSETS_PUBLIC_DIRECTORY.'/'.ltrim($path, '/');
        $version = app(AssetVersionManifest::class)->versionFor(self::staticAssetsSourceDirectory().'/'.ltrim($path, '/'));

        return $version === null ? asset($relative) : asset($relative).'?v='.$version;
    }

    public static function staticAssetsSourceDirectory(): string
    {
        return __DIR__.'/../../resources/dist/assets';
    }

    public static function staticAssetsPublicDirectory(): string
    {
        return public_path(self::STATIC_ASSETS_PUBLIC_DIRECTORY);
    }

    public static function shouldPublishStalePackageAssets(?bool $runningInConsole = null): bool
    {
        $runningInConsole ??= app()->runningInConsole();

        // Console (filament:assets, deploy hooks) may always sync.
        if ($runningInConsole) {
            return true;
        }

        // HTTP auto-publish is local/testing DX only — never staging/production
        // (avoids re-copying large static trees onto ephemeral disks every request).
        return in_array(app()->environment(), ['local', 'testing'], true);
    }

    public static function publishStalePackageAssets(Filesystem $filesystem): void
    {
        (new ComponentAssetPublisher(ComponentAssetCatalog::registry(), $filesystem))->publish();
    }
}
