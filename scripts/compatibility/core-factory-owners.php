<?php

declare(strict_types=1);

// Uses the existing isolated schema/asset boot harness; no app database is touched.
require __DIR__.'/shared-overlay-v4-contract.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ScheduleColumn;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetArtifact;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RenderedFactoryOwners;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;

$directory = sys_get_temp_dir().'/fff-factory-owners-'.bin2hex(random_bytes(5));
mkdir($directory, 0700);
$originalRegistry = app(ComponentRegistry::class);
try {
    $code = 'export default () => ({ init() {} });';
    file_put_contents($directory.'/field.js', $code);
    $assets = new AssetPackage(new AssetManifest('example/factory-owners', [
        'entry' => new AssetArtifact('field.js', ArtifactKind::Module, hash('sha256', $code), strlen($code)),
    ], entries: ['owner-field' => 'entry']), $directory);
    $package = new ComponentPackage($assets, 'owner', '1.0.0');
    $family = new ComponentFamily($package, 'owner-family', ['field' => 'owner-field', 'column' => 'owner-column']);
    $registry = ComponentRegistry::builtIn();
    $registrar = new ComponentRegistrar($registry);
    $registrar->collectComponentFamily($family, [
        new ComponentDefinition('owner-field', ComponentKind::Field, entry: 'owner-field', uses: ['select-field'],
            surface: new ComponentSurface($family, ComponentKind::Field, ScheduleField::class)),
        new ComponentDefinition('owner-column', ComponentKind::Column,
            surface: new ComponentSurface($family, ComponentKind::Column, ScheduleColumn::class)),
    ]);
    $registrar->completeCollection();
    $registry->freeze();
    app()->instance(ComponentRegistry::class, $registry);
    $owners = RenderedFactoryOwners::forComponents(['owner-field']);
    $ownUrl = FlexFieldAssets::alpineEntrySrc('owner-field');
    $selectUrl = FlexFieldAssets::alpineEntrySrc('select-field');
    locationCheck(count($owners) === 2 && $owners[$ownUrl] === [
        'package' => 'example/factory-owners', 'component' => 'owner-field', 'export' => 'default', 'abi' => 1,
    ], 'Manifest-backed family factory owner differs from its installation');
    locationCheck($owners[$selectUrl]['package'] === 'janczakb/filament-flex-fields'
        && $owners[$selectUrl]['component'] === 'select-field', 'Composed Select inherited the outer package identity');
    locationCheck(RenderedFactoryOwners::forComponents(['owner-column']) === [], 'Readonly column inherited field factories');
    $country = RenderedFactoryOwners::forComponents(['country-field']);
    locationCheck(count($country) === 1 && $country[$selectUrl]['component'] === 'select-field', 'Wrapper-only Country lost its actual module owner');
    locationCheck(RenderedFactoryOwners::forComponents(['owner-field', 'owner-field']) === $owners, 'Repeated composition duplicated factory metadata');
    $html = Blade::render('<x-filament-flex-fields::assets component="owner-field" consumer="first" /><x-filament-flex-fields::assets component="owner-field" consumer="second" />');
    preg_match_all("/data-fff-factory-owners='([^']+)'/", $html, $matches);
    locationCheck(count($matches[1]) === 2 && json_decode($matches[1][0], true, flags: JSON_THROW_ON_ERROR) === $owners,
        'Independent public Blade boundaries lost their current owner maps');
    locationCheck(! str_contains(json_encode($owners, JSON_THROW_ON_ERROR), $directory), 'Private installation root leaked into rendered factory owners');
    URL::forceRootUrl('https://another-host.test/nested');
    $second = RenderedFactoryOwners::forComponents(['owner-field']);
    locationCheck(array_keys($second) !== array_keys($owners)
        && array_values($second) === array_values($owners), 'Rendered URLs leaked across request/host context');
    locationCheck(ComponentAssetPlan::for('owner-column')->chunks === [], 'Readonly plan changed while adding owner metadata');
    echo 'PASS: manifest/builtin/composed factory owners, independent field boundaries, readonly column, repeat dedup and host-context isolation.'.PHP_EOL;
} finally {
    URL::forceRootUrl(null);
    app()->instance(ComponentRegistry::class, $originalRegistry);
    (new Filesystem)->deleteDirectory($directory);
}
