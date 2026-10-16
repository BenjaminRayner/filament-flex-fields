<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;
use Filament\Support\Assets\AssetManager;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->publicationRoot = sys_get_temp_dir().'/fff-publication-'.bin2hex(random_bytes(8));
    $this->publicationFiles = new Filesystem;
    $this->publicationFiles->makeDirectory($this->publicationRoot.'/source', 0755, true);
    $this->publicationFiles->makeDirectory($this->publicationRoot.'/public');
    $this->previousAssets = FilamentAsset::getFacadeRoot();
    FilamentAsset::swap(new AssetManager);
    $this->publicationRegistry = new ComponentRegistry;
    $this->publicationRegistry->register(new ComponentDefinition(
        id: 'publication-fixture',
        kind: ComponentKind::Primitive,
        publications: [new PublishedResource('files', $this->publicationRoot.'/source', 'filament-flex-fields-assets/fixture')],
    ));
    $this->publisher = new ComponentAssetPublisher($this->publicationRegistry, $this->publicationFiles);
    file_put_contents($this->publicationRoot.'/source/first.json', '{"value":1}');
});

afterEach(function (): void {
    FilamentAsset::swap($this->previousAssets);
    $this->publicationFiles->deleteDirectory($this->publicationRoot);
});

it('publishes atomically and leaves an identical publication unchanged', function (): void {
    expect($this->publisher->publish($this->publicationRoot.'/public'))->toBe(['copied' => 1, 'unchanged' => 0])
        ->and($this->publisher->publish($this->publicationRoot.'/public'))->toBe(['copied' => 0, 'unchanged' => 1])
        ->and(file_get_contents($this->publicationRoot.'/public/filament-flex-fields-assets/fixture/first.json'))->toBe('{"value":1}')
        ->and(glob($this->publicationRoot.'/public/filament-flex-fields-assets/fixture/.fff-publish-*'))->toBe([]);
});

it('repairs corrupt destination contents despite unchanged size and timestamp', function (): void {
    $this->publisher->publish($this->publicationRoot.'/public');
    $destination = $this->publicationRoot.'/public/filament-flex-fields-assets/fixture/first.json';
    $modified = filemtime($destination);
    file_put_contents($destination, '{"value":9}');
    touch($destination, $modified);

    expect($this->publisher->publish($this->publicationRoot.'/public'))->toBe(['copied' => 1, 'unchanged' => 0])
        ->and(file_get_contents($destination))->toBe('{"value":1}');
});

it('rejects duplicate destinations before copying any asset', function (): void {
    $asset = Js::make('collision', $this->publicationRoot.'/source/first.json');
    FilamentAsset::register([$asset, clone $asset], FilamentFlexFieldsPlugin::PACKAGE_NAME);
    expect(fn () => $this->publisher->publish($this->publicationRoot.'/public'))
        ->toThrow(RuntimeException::class, 'Multiple asset sources')
        ->and(file_exists($this->publicationRoot.'/public/'.$asset->getRelativePublicPath()))->toBeFalse()
        ->and(file_exists($this->publicationRoot.'/public/filament-flex-fields-assets/fixture/first.json'))->toBeFalse();
});

it('rejects symlink destinations even when a previous publication was successful', function (): void {
    $this->publisher->publish($this->publicationRoot.'/public');
    $destination = $this->publicationRoot.'/public/filament-flex-fields-assets/fixture/first.json';
    unlink($destination);
    symlink($this->publicationRoot.'/source/first.json', $destination);
    expect(fn () => $this->publisher->publish($this->publicationRoot.'/public'))
        ->toThrow(RuntimeException::class, 'unsafe symlink');
});

it('rejects an ancestor symlink before copying other planned resources', function (): void {
    file_put_contents($this->publicationRoot.'/source/second.json', '{}');
    $this->publicationFiles->makeDirectory($this->publicationRoot.'/outside');
    symlink($this->publicationRoot.'/outside', $this->publicationRoot.'/public/filament-flex-fields-assets');
    expect(fn () => $this->publisher->publish($this->publicationRoot.'/public'))
        ->toThrow(RuntimeException::class, 'metadata directory')
        ->and(file_exists($this->publicationRoot.'/outside/fixture/first.json'))->toBeFalse();
});

it('rejects missing declared resources before copying an earlier valid source', function (): void {
    FilamentAsset::register([Js::make('missing-fixture', $this->publicationRoot.'/missing.js')], FilamentFlexFieldsPlugin::PACKAGE_NAME);
    expect(fn () => $this->publisher->publish($this->publicationRoot.'/public'))
        ->toThrow(RuntimeException::class, 'Missing registered asset')
        ->and(file_exists($this->publicationRoot.'/public/filament-flex-fields-assets/fixture/first.json'))->toBeFalse();
});
