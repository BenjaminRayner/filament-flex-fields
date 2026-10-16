<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\Assets\FlexFieldsAlpineComponent;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\AlpineBuildManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\FirstPaintScripts;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\AssetRegistration;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Support\Assets\AssetManager;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Filesystem\Filesystem;

function firstPaintCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$state = $app->make(FirstPaintScripts::class);
$segment = [FlexFieldAssets::segmentOverflowSsrInlineContents(), FlexFieldAssets::segmentOverflowSsrInlineContents()];
$timezone = [FlexFieldAssets::timezoneBrowserSsrInlineContents(), FlexFieldAssets::timezoneBrowserSsrInlineContents()];
foreach (['segment' => $segment, 'timezone' => $timezone] as $name => $scripts) {
    firstPaintCheck(str_starts_with($scripts[0], '// fff-first-paint:'.$name.':'), 'First boundary did not emit synchronous owner.');
    firstPaintCheck(! str_contains($scripts[1], 'Object.freeze') && strlen($scripts[1]) < 400, 'Repeated boundary copied implementation.');
    firstPaintCheck(str_contains($scripts[1], 'api.paint(document.currentScript)'), 'Repeated boundary lost its own local boot.');
    firstPaintCheck(str_contains($scripts[1], 'api.version'), 'Thin invocation lost compatibility protection.');
}
$state->reset();
firstPaintCheck(FlexFieldAssets::segmentOverflowSsrInlineContents() === $segment[0], 'Explicit standalone reset did not emit owner.');
$app->forgetScopedInstances();
firstPaintCheck($app->make(FirstPaintScripts::class) !== $state, 'Emission state leaked across request scopes.');
firstPaintCheck(FlexFieldAssets::segmentOverflowSsrInlineContents() === $segment[0], 'Next response omitted synchronous owner.');
firstPaintCheck(FlexFieldAssets::timezoneBrowserSsrInlineContents() === $timezone[0], 'Timezone emission leaked across scopes.');
$manifest = new AlpineBuildManifest;
$registered = (new AssetRegistration)->scripts();
foreach ([FlexFieldAssets::SEGMENT_OVERFLOW_SSR_SCRIPT_ID => 'segment', FlexFieldAssets::TIMEZONE_BROWSER_SSR_BOOT_SCRIPT_ID => 'timezone'] as $id => $name) {
    $asset = array_values(array_filter($registered, fn ($asset): bool => $asset->getId() === $id))[0];
    $chunk = $manifest->firstPaintFile($name);
    firstPaintCheck(str_ends_with($asset->getRelativePublicPath(), '/components/'.$chunk), 'Helper publishes duplicate alias URL.');
    firstPaintCheck(! str_contains($asset->getSrc(), '?'), 'Classic helper alias differs from native ESM module identity.');
}
$previousAssets = FilamentAsset::getFacadeRoot();
$publicationRoot = sys_get_temp_dir().'/fff-alias-'.bin2hex(random_bytes(8));
$filesystem = new Filesystem;
$filesystem->makeDirectory($publicationRoot);
$publicationRoot = realpath($publicationRoot) ?: throw new RuntimeException('Cannot resolve probe root.');
$alias = array_values(array_filter($registered, fn ($asset): bool => $asset->getId() === FlexFieldAssets::SEGMENT_OVERFLOW_SSR_SCRIPT_ID))[0];
$ownerId = $alias->getPublicationOwnerId();
$owner = FlexFieldsAlpineComponent::make($ownerId, $alias->getPath());
$publisher = new ComponentAssetPublisher(new ComponentRegistry, $filesystem);
$plan = new ReflectionMethod($publisher, 'plan');
try {
    foreach ([[$alias, $owner], [$owner, $alias]] as $assets) {
        FilamentAsset::swap(new AssetManager);
        FilamentAsset::register($assets, FilamentFlexFieldsPlugin::PACKAGE_NAME);
        firstPaintCheck(count($plan->invoke($publisher, $publicationRoot)) === 1, 'Declared alias duplicated its publication.');
    }
    $copy = $publicationRoot.'/copied-owner.js';
    copy($alias->getPath(), $copy);
    $differentSource = FlexFieldsAlpineComponent::make($ownerId, $copy);
    foreach ([[$alias], [$alias, $owner, clone $owner], [$alias, clone $alias, $owner], [$alias, $differentSource]] as $assets) {
        FilamentAsset::swap(new AssetManager);
        FilamentAsset::register($assets, FilamentFlexFieldsPlugin::PACKAGE_NAME);
        $rejected = false;
        try {
            $plan->invoke($publisher, $publicationRoot);
        } catch (RuntimeException) {
            $rejected = true;
        }
        firstPaintCheck($rejected, 'Invalid alias/collision was accepted.');
    }
} finally {
    FilamentAsset::swap($previousAssets);
    $filesystem->deleteDirectory($publicationRoot);
}
$groups = 5;
if (in_array('--json', $argv, true)) {
    echo json_encode(compact('segment', 'timezone', 'groups'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} else {
    echo 'First-paint rendering: '.$groups.' groups pass; one implementation per response, request isolation, local boot and exact helper/module URL.'.PHP_EOL;
}
