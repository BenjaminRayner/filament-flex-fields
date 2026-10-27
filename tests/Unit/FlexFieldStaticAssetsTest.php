<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BarcodeScannerField;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

it('publishes and verifies nested assets using native filesystem paths', function (): void {
    $disk = Storage::fake('asset-publication');
    $publisher = new ComponentAssetPublisher(ComponentAssetCatalog::registry(), new Filesystem);
    $release = app(AssetVersionManifest::class)->release();

    $published = $publisher->publish($disk->path(''));

    expect($published['copied'])->toBeGreaterThan(0);
    $disk->assertExists('filament-flex-fields-assets/core/'.$release.'/assets/barcode-scanner-field/barcode-scan-success.mp3');

    expect($publisher->publish($disk->path('')))->toBe([
        'copied' => 0,
        'unchanged' => $published['copied'] + $published['unchanged'],
    ]);
});

it('builds versioned urls for published static assets', function () {
    $url = FlexFieldAssets::assetUrl('nps-field/emojis/0.webp');

    expect($url)
        ->toContain('filament-flex-fields-assets/nps-field/emojis/0.webp')
        ->toMatch('/\?v=[a-f0-9]{64}$/');
});

it('resolves barcode scanner beep url through the static asset helper', function () {
    $url = BarcodeScannerField::make('code')->getBeepUrl();
    $release = app(AssetVersionManifest::class)->release();

    expect($url)
        ->toBe(asset('filament-flex-fields-assets/core/'.$release.'/assets/barcode-scanner-field/barcode-scan-success.mp3'))
        ->not->toContain('?');
});

it('does not publish stale package assets on production web requests', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(FlexFieldAssets::shouldPublishStalePackageAssets(runningInConsole: false))->toBeFalse();
});

it('still publishes stale package assets on production console commands', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(FlexFieldAssets::shouldPublishStalePackageAssets(runningInConsole: true))->toBeTrue();
});

it('does not publish stale package assets on staging web requests', function () {
    app()->detectEnvironment(fn (): string => 'staging');

    expect(FlexFieldAssets::shouldPublishStalePackageAssets(runningInConsole: false))->toBeFalse();
});
