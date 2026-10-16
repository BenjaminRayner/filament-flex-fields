<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BarcodeScannerField;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;

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
