<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Assets\StylesheetCascade;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Support\Facades\FilamentAsset;
use LogicException;

/** Metadata does not acquire CSS or activate optional capabilities. */
final class StylesheetOrderCatalog
{
    public function json(): string
    {
        $styles = StylesheetCascade::forGraph(ComponentAssetCatalog::registry()->compiledGraph())->all();
        $catalog = FlexFieldAssets::isPlaygroundRequest();
        if (count($styles) + 1 + ($catalog ? 1 : 0) > 2048) {
            throw new LogicException('Stylesheet cascade exceeds its resource budget.');
        }
        $urls = [FilamentAsset::getStyleHref(FlexFieldAssets::CORE_STYLESHEET_ID, FilamentFlexFieldsPlugin::PACKAGE_NAME)];
        foreach ($styles as $style) {
            $urls[] = FlexFieldAssets::stylesheetHref($style);
        }
        if ($catalog) {
            $urls[] = FlexFieldAssets::playgroundStylesheetHref();
        }
        if (count($urls) > 2048 || count(array_unique($urls)) !== count($urls)) {
            throw new LogicException('Invalid stylesheet cascade resource ownership or metadata budget.');
        }
        $json = json_encode(['format' => 1, 'stylesheets' => $urls], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 262144) {
            throw new LogicException('Stylesheet cascade metadata exceeds its 256 KiB budget.');
        }

        return $json;
    }
}
