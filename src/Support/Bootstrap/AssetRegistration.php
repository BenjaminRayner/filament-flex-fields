<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap;

use Bjanczak\FilamentFlexFields\Assets\FirstPaintScript;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsAlpineComponent;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsCoreCss;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsCss;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsJs;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\AlpineBuildManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\DevelopmentAssetFallback;
use Bjanczak\FilamentFlexFields\Support\Assets\BuiltInComponentIndex;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Filament\Support\Assets\Js;
use RuntimeException;

/** Publishes asset identities; component assets remain loaded on demand. */
final class AssetRegistration
{
    public function __construct(private readonly ?string $distRoot = null) {}

    /** @var array<string, string> */
    public const SCRIPT_FILES = [
        FlexFieldAssets::FFF_LOAD_BOOTSTRAP_SCRIPT_ID => 'core/flex-fff-load-bootstrap.js',
        FlexFieldAssets::ASSET_INJECTOR_SCRIPT_ID => 'core/flex-field-asset-injector.js',
        FlexFieldAssets::ASSET_INSPECTOR_SCRIPT_ID => 'core/flex-field-asset-inspector.js',
        FlexFieldAssets::FLEX_RICH_EDITOR_PASTE_EXTENSION_SCRIPT_ID => 'support/flex-rich-editor-paste-extension.js',
        FlexFieldAssets::FLEX_RICH_EDITOR_BLOCK_IMAGE_EXTENSION_SCRIPT_ID => 'support/flex-rich-editor-block-image-extension.js',
        FlexFieldAssets::FLEX_RICH_EDITOR_YOUTUBE_EXTENSION_SCRIPT_ID => 'support/flex-rich-editor-youtube-extension.js',
    ];

    /**
     * @return list<FlexFieldsCss>
     */
    public function stylesheets(): array
    {
        $distPath = $this->root().'/css';

        $assets = [
            FlexFieldsCoreCss::make(FlexFieldAssets::CORE_STYLESHEET_ID, $distPath.'/core.css'),
        ];

        if (FlexFieldsConfig::isPlaygroundEnabled()) {
            $assets[] = FlexFieldsCss::make(FlexFieldAssets::PLAYGROUND_STYLESHEET_ID, $distPath.'/playground.css')->loadedOnRequest();

        }

        foreach (BuiltInComponentIndex::LAZY_COMPONENT_STYLESHEETS as $component) {
            $assets[] = FlexFieldsCss::make(
                FlexFieldAssets::stylesheetId($component),
                $distPath.'/'.$component.'.css',
            )->loadedOnRequest();
        }

        foreach ($assets as $asset) {
            $this->assertFile($asset->getPath());
        }

        return $assets;
    }

    /**
     * @return list<Js>
     */
    public function scripts(): array
    {
        // Registered once; render hooks decide when the script tag is emitted.
        $scripts = array_map(function (string $id, string $file): Js {
            $path = $this->root().'/'.$file;
            $this->assertFile($path);

            return FlexFieldsJs::make($id, $path)->loadedOnRequest();
        }, array_keys(self::SCRIPT_FILES), array_values(self::SCRIPT_FILES));
        $manifest = new AlpineBuildManifest($this->root().'/components/alpine-manifest.json');
        foreach ([FlexFieldAssets::SEGMENT_OVERFLOW_SSR_SCRIPT_ID => 'segment', FlexFieldAssets::TIMEZONE_BROWSER_SSR_BOOT_SCRIPT_ID => 'timezone', 'flex-fields-scoped-style-readiness' => 'readiness'] as $id => $name) {
            $file = $manifest->firstPaintFile($name);
            $path = $this->root().'/components/'.$file;
            $this->assertFile($path);
            $scripts[] = FirstPaintScript::make($id, $path)->chunk($file)->loadedOnRequest();
        }

        return $scripts;
    }

    /**
     * @return list<FlexFieldsAlpineComponent>
     */
    public function alpineComponents(): array
    {
        $distPath = $this->root().'/components';
        $assets = [];

        foreach (FlexFieldAssets::alpineEntryNames() as $entry) {
            $path = $distPath.'/'.$entry.'.js';

            if (! $this->assertFile($path)) {
                continue;
            }

            $assets[] = FlexFieldsAlpineComponent::make($entry, $path);
        }

        return $assets;
    }

    /**
     * @return list<FlexFieldsAlpineComponent>
     */
    public function alpineChunks(): array
    {
        $distPath = $this->root().'/components';
        $assets = [];

        foreach (FlexFieldAssets::alpineSharedChunkNames() as $chunk) {
            $path = $distPath.'/'.$chunk;

            if (! $this->assertFile($path)) {
                continue;
            }

            $id = str_replace('.js', '', $chunk);

            $assets[] = FlexFieldsAlpineComponent::make($id, $path)->contentHashed()->loadedOnRequest();
        }

        return $assets;
    }

    private function root(): string
    {
        return $this->distRoot ?? __DIR__.'/../../../resources/dist';
    }

    private function assertFile(?string $path): bool
    {
        if ($path === null || ! is_file($path) || ! is_readable($path) || is_link($path)) {
            DevelopmentAssetFallback::report(new RuntimeException('Missing or unsafe declared asset: '.($path ?? '(none)')));

            return false;
        }

        return true;
    }
}
