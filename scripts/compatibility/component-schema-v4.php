<?php

declare(strict_types=1);

require __DIR__.'/shared-overlay-v4-contract.php';

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});

use Bjanczak\FilamentFlexFields\Assets\FlexFieldsCss;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Infolists\Components\ScheduleEntry;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ScheduleColumn;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetArtifact;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifestCompiler;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\PackageArtifactReference;
use Bjanczak\FilamentFlexFields\Support\Assets\PortableAssetRegistry;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PackageReleasePublisher;
use Bjanczak\FilamentFlexFields\Support\Components\Compilation\ComponentFamilyCompiler;
use Bjanczak\FilamentFlexFields\Support\Components\Compilation\ComponentGraphCompiler;
use Bjanczak\FilamentFlexFields\Support\Components\Compilation\ComponentProjections;
use Bjanczak\FilamentFlexFields\Support\Components\Compilation\PackageAssetBudget;
use Bjanczak\FilamentFlexFields\Support\Components\Compilation\PackageAssetCompiler;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistration;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Forms\Components\Field;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

abstract class SchemaContractAbstractField extends Field {}

$registry = ComponentRegistry::builtIn();
locationCheck(count($registry->all()) >= 89, 'Incomplete built-in schemas');
foreach ($registry->all() as $id => $definition) {
    locationCheck(count($registry->stylesFor($id)) === count(array_unique($registry->stylesFor($id))), 'Duplicate style dependency '.$id);
}
$styleOnly = new ComponentDefinition('probe-styles', ComponentKind::Display, stylesFrom: ['select-field']);
$full = new ComponentDefinition('probe-composed', ComponentKind::Field, uses: ['select-field', 'flex-text-input']);
$registry->register($styleOnly);
$registry->register($full);
locationCheck($registry->runtimeComponents('probe-styles') === ['probe-styles'], 'Style reuse loaded unused JS');
locationCheck(in_array('select-field', $registry->runtimeComponents('probe-composed'), true), 'Full composition dropped runtime');
try {
    $cycle = new ComponentRegistry;
    $cycle->register(new ComponentDefinition('first', ComponentKind::Field, uses: ['second']));
    $cycle->register(new ComponentDefinition('second', ComponentKind::Field, uses: ['first']));
    $cycle->validate();
    throw new RuntimeException('Cycle admitted');
} catch (LogicException) {
}
try {
    $duplicate = new ComponentRegistry;
    $duplicate->register(new ComponentDefinition('first', ComponentKind::Field, styles: ['shared']));
    $duplicate->register(new ComponentDefinition('second', ComponentKind::Field, styles: ['shared']));
    $duplicate->validate();
    throw new RuntimeException('Duplicate stylesheet owner admitted');
} catch (LogicException) {
}
locationCheck(! in_array('select-field', ComponentAssetPlan::for('flex-color-picker')->stylesheets, true), 'Optional Select loaded unconditionally');
locationCheck(in_array('select-field', ComponentAssetPlan::for('flex-color-picker', ['format-selector'])->stylesheets, true), 'Format Select capability omitted');
locationReject(fn () => new PublishedResource('escaped', __DIR__, 'filament-flex-fields-assets/../private'));

$directory = sys_get_temp_dir().'/fff-schema-probe-'.bin2hex(random_bytes(5));
mkdir($directory.'/resources', 0755, true);
mkdir($directory.'/public', 0755, true);
file_put_contents($directory.'/probe.css', '.probe { color: red }');
file_put_contents($directory.'/resources/sample.json', '{"release":1}');
// The application registry is frozen after boot. This CLI-only harness installs
// an explicit builder to exercise publication; it never thaws the boot snapshot.
app()->instance(ComponentRegistry::class, $registry);
app()->instance(ComponentRegistrar::class, new ComponentRegistrar($registry));
$definition = new ComponentDefinition('probe-custom', ComponentKind::Field, styles: ['probe-custom'], uses: ['select-field'], publications: [new PublishedResource('data', $directory.'/resources', 'filament-flex-fields-assets/probe-custom')]);
app(ComponentRegistrar::class)->register($definition, ['probe-custom' => $directory.'/probe.css']);
$plan = ComponentAssetPlan::for('probe-custom');
locationCheck(in_array('probe-custom', $plan->stylesheets, true) && in_array('select-field.js', $plan->chunks, true), 'Custom plan missing assets');
$publisher = app(ComponentAssetPublisher::class);
$originalPublic = app()->publicPath();
$originalVersions = app(AssetVersionManifest::class);
app()->usePublicPath($directory.'/public');
app()->instance(AssetVersionManifest::class, new AssetVersionManifest);
try {
    $rejectAsset = static function (Closure $operation): void {
        try {
            $operation();
        } catch (InvalidArgumentException|RuntimeException) {
            return;
        }
        throw new RuntimeException('Invalid compatibility publication was accepted.');
    };
    $compatibilityStyle = FlexFieldsCss::make(FlexFieldAssets::stylesheetId('probe-custom'), $directory.'/probe.css')->package('janczakb/filament-flex-fields');
    $rejectAsset(fn () => $compatibilityStyle->getVersion());
    $first = $publisher->publish($directory.'/public');
    app()->instance(AssetVersionManifest::class, new AssetVersionManifest);
    $customHtml = Blade::render('<x-filament-flex-fields::assets component="probe-custom" consumer="custom.1" />');
    locationCheck(str_contains($customHtml, 'data-fff-asset-consumer="probe-custom"'), 'Custom public Blade ownership absent');
    $oldDigest = $compatibilityStyle->getVersion();
    locationCheck($oldDigest === hash_file('sha256', $directory.'/probe.css'), 'Compatibility asset does not use published content identity');
    $second = $publisher->publish($directory.'/public');
    locationCheck($first['copied'] > 0 && $second['copied'] === 0, 'Publication is not idempotent');
    $oldTime = filemtime($directory.'/resources/sample.json');
    file_put_contents($directory.'/resources/sample.json', '{"release":2}');
    touch($directory.'/resources/sample.json', $oldTime);
    $third = $publisher->publish($directory.'/public');
    locationCheck($third['copied'] === 1 && file_get_contents($directory.'/public/filament-flex-fields-assets/probe-custom/sample.json') === '{"release":2}', 'Same-mtime source update omitted');
    $cssTime = filemtime($directory.'/probe.css');
    file_put_contents($directory.'/probe.css', '.probe { color: tan }');
    touch($directory.'/probe.css', $cssTime);
    locationCheck(strlen('.probe { color: tan }') === strlen('.probe { color: red }'), 'Compatibility fixture must preserve byte count');
    $publisher->publish($directory.'/public');
    locationCheck($compatibilityStyle->getVersion() === $oldDigest, 'An existing request snapshot mutated during publication');
    app()->instance(AssetVersionManifest::class, new AssetVersionManifest);
    locationCheck($compatibilityStyle->getVersion() !== $oldDigest && $compatibilityStyle->getVersion() === hash_file('sha256', $directory.'/probe.css'), 'Same-size same-mtime compatibility edit retained its URL identity');
    foreach ([['../escape.css' => ['digest' => str_repeat('a', 64), 'size' => 1, 'modified' => 1]], ['safe.css' => ['digest' => 'invalid', 'size' => 1, 'modified' => 1]], ['safe.css' => ['digest' => str_repeat('a', 64), 'size' => -1, 'modified' => 1]]] as $invalid) {
        $rejectAsset(fn () => AssetVersionManifest::encodePublicationStamps($invalid));
    }
} finally {
    app()->usePublicPath($originalPublic);
    app()->instance(AssetVersionManifest::class, $originalVersions);
}
echo 'PASS: all built-in schemas, strict dependency/ownership validation, optional capabilities, public custom registration/rendering and atomic/idempotent content-based publication.'.PHP_EOL;

// Catalog route detection must remain scoped after the public URL rename.
$originalRequest = app('request');
try {
    foreach ([
        ['/flex-fields', true, null],
        ['/admin/flex-fields/index', true, 'index'],
        ['/admin/tenant/flex-fields/asset-diagnostics-forms', true, 'asset-diagnostics-forms'],
        ['/admin/flex-fields-playground/index', false, null],
        ['/admin/not-flex-fields/index', false, null],
        ['/admin/flex-fields-extra/index', false, null],
    ] as [$path, $catalogRequest, $componentSlug]) {
        app()->instance('request', Request::create($path));
        locationCheck(FlexFieldAssets::isPlaygroundRequest() === $catalogRequest, 'Incorrect catalog scope for '.$path);
        locationCheck(FlexFieldAssets::resolvePlaygroundSlugFromRequest() === $componentSlug, 'Incorrect component slug for '.$path);
    }
} finally {
    app()->instance('request', $originalRequest);
}
echo "PASS: canonical catalog paths and component slugs stay scoped; legacy/lookalike routes do not load gallery assets.\n";

// Package manifest compilation, registration, plan resolution and publication
// are exercised in the same isolated Laravel/Filament application.
$manifestRoot = sys_get_temp_dir().'/fff-manifest-probe-'.bin2hex(random_bytes(5));
mkdir($manifestRoot.'/dist/chunks', 0755, true);
mkdir($manifestRoot.'/dist/workers', 0755, true);
mkdir($manifestRoot.'/dist/styles', 0755, true);
$compiler = new AssetManifestCompiler;
$draftArtifact = static fn (string $path, string $type, array $imports = [], array $dynamicImports = [], array $resources = []): array => [
    'path' => $path, 'type' => $type, 'imports' => $imports, 'dynamicImports' => $dynamicImports, 'resources' => $resources,
];
$specification = [
    'format' => 1, 'package' => 'example/split-component',
    'artifacts' => [
        'entry' => $draftArtifact('entry.js', 'module', ['shared'], ['lazy'], ['worker', 'style']),
        'shared' => $draftArtifact('chunks/shared.js', 'module', ['entry']), // Valid native ESM cycle.
        'lazy' => $draftArtifact('chunks/lazy.js', 'module', ['shared']),
        'worker' => $draftArtifact('workers/capture.js', 'worker', ['shared'], [], ['wasm']),
        'wasm' => $draftArtifact('workers/capture.wasm', 'wasm'),
        'style' => $draftArtifact('styles/component.css', 'stylesheet', ['base'], [], ['font']),
        'base' => $draftArtifact('styles/base.css', 'stylesheet'),
        'font' => $draftArtifact('styles/font.woff2', 'font'),
        'helper' => $draftArtifact('helper.js', 'script'),
    ],
    'entries' => ['split-field' => 'entry', 'split-lazy' => 'lazy'], 'styles' => ['split-field' => 'style'], 'helpers' => ['split-ssr' => 'helper'],
];
$specificationPath = $manifestRoot.'/build-spec.json';
$writeSpecification = static function (array $specification) use ($specificationPath): void {
    file_put_contents($specificationPath, json_encode($specification, JSON_THROW_ON_ERROR));
};
$manifestReject = static function (Closure $operation, string $message): void {
    try {
        $operation();
    } catch (LogicException|RuntimeException|JsonException) {
        return;
    }
    throw new RuntimeException($message);
};
try {
    $contents = [
        'entry' => 'import { shared } from "./chunks/shared.js"; export const owner = "fixture"; export async function run() { const lazy = await import("./chunks/lazy.js"); const worker = new Worker(new URL("./workers/capture.js", import.meta.url), { type: "module" }); try { const result = await new Promise((resolve, reject) => { worker.onmessage = event => resolve(event.data); worker.onerror = reject; worker.postMessage("run"); }); return { shared: shared(), lazy: lazy.value(), worker: result }; } finally { worker.terminate(); } } export default function () { return { init() {} }; }',
        'shared' => 'import { owner } from "../entry.js"; export function shared() { return owner; }',
        'lazy' => 'import { shared } from "./shared.js"; export function value() { return shared() + "-lazy"; }',
        'worker' => 'import { shared } from "../chunks/shared.js"; self.onmessage = async () => { const bytes = await (await fetch(new URL("./capture.wasm", import.meta.url))).arrayBuffer(); await WebAssembly.compile(bytes); self.postMessage({ owner: shared(), wasm: true }); };',
        'wasm' => "\0asm\x01\0\0\0",
        'style' => '@import "./base.css"; .build-fixture { color: red; }',
        'base' => '.fixture-base { display: block; }',
        'font' => 'fixture-binary-font',
        'helper' => 'globalThis.fixtureSsrLoaded = true;',
    ];
    foreach ($specification['artifacts'] as $id => $record) {
        file_put_contents($manifestRoot.'/dist/'.$record['path'], $contents[$id]);
    }
    $writeSpecification($specification);
    $manifest = $compiler->build($specificationPath, $manifestRoot.'/dist');
    $compiler->verifySources($manifest, $manifestRoot.'/dist');
    locationCheck($manifest->eagerArtifactsFor('split-field') === ['entry', 'shared', 'style', 'base'], 'Lazy module/worker became eager or static CSS closure was lost');
    file_put_contents($manifestRoot.'/manifest.json', $manifest->toJson());
    $roundTrip = $compiler->load($manifestRoot.'/manifest.json');
    locationCheck($roundTrip->toJson() === $manifest->toJson(), 'Manifest round trip changed release metadata');
    $process = proc_open([PHP_BINARY, __DIR__.'/../assets/compile-package-manifest.php', $specificationPath,
        $manifestRoot.'/dist', $manifestRoot.'/cli-manifest.json'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    locationCheck(is_resource($process), 'Cannot launch the manifest build tool');
    $cliOutput = stream_get_contents($pipes[1]);
    $cliError = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    locationCheck(proc_close($process) === 0 && $cliError === '', 'Manifest build tool failed: '.$cliError);
    locationCheck(json_decode($cliOutput, true, flags: JSON_THROW_ON_ERROR)['release'] === $manifest->release
        && $compiler->load($manifestRoot.'/cli-manifest.json')->release === $manifest->release, 'CLI manifest differs from compiler output');
    locationCheck((glob($manifestRoot.'/.fff-manifest-*') ?: []) === [], 'Build tool retained temporary output files');
    $reordered = $specification;
    $reordered['artifacts'] = array_reverse($reordered['artifacts'], true);
    $writeSpecification($reordered);
    locationCheck($compiler->build($specificationPath, $manifestRoot.'/dist')->release === $manifest->release, 'Manifest map order changed the release identity');

    $package = new AssetPackage($manifest, $manifestRoot.'/dist');
    $packageDefinitions = [
        new ComponentDefinition('split-style', ComponentKind::Primitive, styles: ['split-field']),
        new ComponentDefinition('split-field', ComponentKind::Field, stylesFrom: ['split-style'], entry: 'split-field',
            capabilities: ['lazy-panel' => ['split-lazy']], ssrScripts: ['split-ssr']),
        new ComponentDefinition('split-lazy', ComponentKind::Field, stylesFrom: ['split-style'], entry: 'split-lazy'),
        new ComponentDefinition('split-display', ComponentKind::Display, stylesFrom: ['split-style']),
    ];
    $beforeRevision = $registry->revision();
    $registrar = app(ComponentRegistrar::class);
    $manifestReject(fn () => $registrar->register(new ComponentDefinition('split-missing-entry', ComponentKind::Field, entry: 'not-in-manifest'), assetPackage: $package), 'Missing manifest entry accepted');
    $manifestReject(fn () => $registrar->register(new ComponentDefinition('split-missing-helper', ComponentKind::Field, ssrScripts: ['not-in-manifest']), assetPackage: $package), 'Missing manifest helper accepted');
    $manifestReject(fn () => $registrar->register(new ComponentDefinition('split-legacy-missing-helper', ComponentKind::Field, ssrScripts: ['not-registered'])), 'Missing legacy SSR helper accepted');
    foreach (['worker', 'wasm', 'font', 'helper'] as $missingArtifact) {
        $relative = $manifest->artifact($missingArtifact)->path;
        unlink($manifestRoot.'/dist/'.$relative);
        try {
            $candidate = new ComponentRegistry;
            $candidateRegistrar = new ComponentRegistrar($candidate);
            $manifestReject(static function () use ($candidateRegistrar, $package, $packageDefinitions): void {
                $candidateRegistrar->collectPackage($package, $packageDefinitions);
                $candidateRegistrar->completeCollection();
            }, 'Missing installed '.$missingArtifact.' reached a renderable registry');
            locationCheck($candidate->all() === [], 'Missing dependency left partially committed components');
            try {
                $package->sourcePath($missingArtifact);
                throw new LogicException('Missing source accepted');
            } catch (InvalidArgumentException $error) {
                locationCheck(str_contains($error->getMessage(), 'example/split-component -> '.$missingArtifact.' -> '.$relative), 'Missing resource diagnostic omits its package/artifact/path');
            }
        } finally {
            file_put_contents($manifestRoot.'/dist/'.$relative, $contents[$missingArtifact]);
        }
    }
    $manifestReject(fn () => $registry->registerBatch([
        new ComponentDefinition('split-no-css-owner', ComponentKind::Field, entry: 'split-field'),
    ], ['split-no-css-owner' => $package]), 'Eager CSS with no owner accepted');
    locationCheck($registry->revision() === $beforeRevision && $registry->packageAssets()->packages === [], 'Invalid package partially mutated the snapshot');
    $registrar->collectPackage($package, $packageDefinitions);
    locationCheck(! $registry->has('split-field'), 'Package collection became visible before the boot barrier');
    $registrar->completeCollection();
    locationCheck(count($registry->packageAssets()->packages) === 1, 'Shared package acquired multiple owners');
    $normal = ComponentAssetPlan::for('split-field');
    $withOptional = ComponentAssetPlan::for('split-field', ['lazy-panel']);
    locationCheck($normal->chunks === [$package->moduleIdentity('entry').'.js', $package->moduleIdentity('shared').'.js'], 'Static package closure changed or lazy/worker was preloaded');
    locationCheck(in_array($package->moduleIdentity('lazy').'.js', $withOptional->chunks, true), 'Per-instance lazy capability lost its module');
    locationCheck(ComponentAssetPlan::for('split-display')->chunks === [], 'Style-only display imported field JS');
    $entryUrl = FlexFieldAssets::alpineEntrySrc('split-field');
    locationCheck($entryUrl === FlexFieldAssets::alpineChunkSrc($package->moduleIdentity('entry').'.js') && ! str_contains($entryUrl, '?'), 'Preload and import use different package module URLs');
    locationCheck(str_contains(FlexFieldAssets::stylesheetHref('split-field'), '/'.$manifest->release.'/styles/component.css')
        && FlexFieldAssets::stylesheetBytes('split-field') === $manifest->artifact('style')->bytes, 'Package style URL/bytes did not come from its manifest');
    locationCheck(str_contains(FlexFieldAssets::ssrScriptSrc('split-ssr'), '/'.$manifest->release.'/helper.js'), 'Package SSR helper URL missing');
    $portable = PortableAssetRegistry::export();
    $portableEntry = $portable['resources'][$portable['bundles']['split-field']['entry']];
    locationCheck($portable['format'] === 4 && $portableEntry['origin'] === 'package-release'
        && $portableEntry['package'] === $manifest->package && $portableEntry['path'] === 'entry.js'
        && $portableEntry['release'] === $manifest->release, 'Package export pretended to be an internal distribution path');
    locationCheck(in_array('registered:stylesheet:probe-custom', $portable['bundles']['probe-custom']['stylesheets'], true)
        && $portable['resources']['registered:stylesheet:probe-custom']['origin'] === 'registered', 'Legacy custom file pretended to be a portable distribution');
    $serializedPortable = json_encode($portable, JSON_THROW_ON_ERROR);
    locationCheck(! str_contains($serializedPortable, $manifestRoot) && ! str_contains($serializedPortable, 'https://'), 'Portable export retained host/source context');
    locationCheck($portable['packages'][$manifest->package]['release'] === $manifest->release, 'Portable export lost the complete package manifest');
    $packageHtml = Blade::render('<x-filament-flex-fields::assets component="split-field" consumer="split.1" /><x-filament-flex-fields::assets component="split-field" consumer="split.2" />');
    locationCheck(substr_count($packageHtml, 'data-fff-asset-consumer="split-field"') === 2
        && ! str_contains($packageHtml, '/chunks/lazy.js') && ! str_contains($packageHtml, '/workers/capture.js'), 'Independent boundaries or lazy separation broke');
    $foreign = new AssetPackage(new AssetManifest('other/split-component', $manifest->artifacts, $manifest->entries, $manifest->styles, $manifest->helpers), $manifestRoot.'/dist');
    $revision = $registry->revision();
    $manifestReject(fn () => $registry->registerBatch([new ComponentDefinition('split-double-owner', ComponentKind::Display)], ['split-double-owner' => $foreign]), 'One source tree got two package owners');
    locationCheck($registry->revision() === $revision && count($registry->packageAssets()->packages) === 1, 'Rejected package changed active metadata');

    mkdir($manifestRoot.'/public');
    $published = $publisher->publish($manifestRoot.'/public');
    locationCheck($published['copied'] > 0 && $publisher->publish($manifestRoot.'/public')['copied'] === 0, 'Package publication is not idempotent');
    $releasePath = $manifestRoot.'/public/'.$package->publicDirectory();
    foreach ($manifest->artifacts as $id => $artifact) {
        locationCheck(is_file($releasePath.'/'.$artifact->path) && hash_file('sha256', $releasePath.'/'.$artifact->path) === $artifact->sha256, 'Package dependency not published: '.$id);
    }
    locationCheck(is_file($releasePath.'/.release.json') && (glob(dirname($releasePath).'/.fff-stage-*') ?: []) === [], 'Complete release marker or staging cleanup failed');
    config()->set('filament.assets_path', 'nested/assets');
    locationCheck(str_contains(FlexFieldAssets::alpineEntrySrc('split-field'), '/nested/assets/'.$package->publicDirectory()), 'Current-context asset prefix was ignored');
    $publisher->publish($manifestRoot.'/public');
    locationCheck(is_file($manifestRoot.'/public/'.$package->publicDirectory('nested/assets').'/workers/capture.wasm'), 'Nested-prefix release did not publish resources');
    config()->set('filament.assets_path', '');
    $manifestReject(fn () => (new PackageArtifactReference($package, 'entry'))->relativePublicPath('../private'), 'Unsafe package prefix accepted');
    $manifestReject(fn () => $package->publicDirectory(str_repeat('x', 513)), 'Unbounded package prefix accepted');
    $manifestReject(fn () => $package->publicDirectory('CON'), 'Reserved directory prefix accepted');

    // Inject a failed copy into the real publisher, without changing test suites.
    $brokenFilesystem = new class extends Filesystem
    {
        private int $copies = 0;

        public function copy($path, $target)
        {
            return ++$this->copies === 3 ? false : parent::copy($path, $target);
        }
    };
    $brokenPublisher = new PackageReleasePublisher($brokenFilesystem);
    $brokenPublisher->preflight($package, $manifestRoot.'/public', 'failed-copy');
    $manifestReject(fn () => $brokenPublisher->publish($package, $manifestRoot.'/public', 'failed-copy'), 'Copy failure activated an incomplete release');
    $failedPath = $manifestRoot.'/public/'.$package->publicDirectory('failed-copy');
    locationCheck(! file_exists($failedPath) && (glob(dirname($failedPath).'/.fff-stage-*') ?: []) === [], 'Failed publication retained a release or staging tree');
    (new PackageReleasePublisher(app(Filesystem::class)))->publish($package, $manifestRoot.'/public', 'failed-copy');
    locationCheck(is_file($failedPath.'/.release.json'), 'Retry after failed staging did not complete');
    file_put_contents($releasePath.'/unlisted.js', 'unexpected');
    $manifestReject(fn () => $publisher->publish($manifestRoot.'/public'), 'Unlisted output accepted in immutable release');
    unlink($releasePath.'/unlisted.js');
    unlink($releasePath.'/workers/capture.wasm');
    $manifestReject(fn () => $publisher->publish($manifestRoot.'/public'), 'Missing published binary accepted');
    file_put_contents($releasePath.'/workers/capture.wasm', $contents['wasm']);
    symlink($manifestRoot.'/dist', $manifestRoot.'/public/symlink-prefix');
    $manifestReject(fn () => (new PackageReleasePublisher(app(Filesystem::class)))->preflight($package, $manifestRoot.'/public', 'symlink-prefix'), 'Symlink publication prefix accepted');
    unlink($manifestRoot.'/public/symlink-prefix');

    echo "PASS: manifest package collection/atomic rejection, static and optional plans, CSS-only surfaces, independent boundaries, content URLs, metadata sizes, complete publication and subdirectory prefixes.\n";

    $source = $manifestRoot.'/dist/styles/component.css';
    $time = filemtime($source);
    $original = file_get_contents($source);
    file_put_contents($source, str_replace('build', 'other', $original));
    touch($source, $time);
    $changed = $compiler->build($specificationPath, $manifestRoot.'/dist');
    locationCheck($manifest->release !== $changed->release && $manifest->artifact('style')->bytes === $changed->artifact('style')->bytes, 'Same-size, same-mtime update retained the release identity');
    $manifestReject(fn () => $compiler->verifySources($manifest, $manifestRoot.'/dist'), 'Stale bytes accepted by manifest preflight');
    $manifestReject(fn () => $publisher->publish($manifestRoot.'/public'), 'Stale source bytes published over an immutable release');
    $nextPackage = new AssetPackage($changed, $manifestRoot.'/dist');
    $nextRegistry = ComponentRegistry::builtIn();
    $nextRegistrar = new ComponentRegistrar($nextRegistry);
    $nextRegistrar->collectPackage($nextPackage, $packageDefinitions);
    $nextRegistrar->completeCollection();
    (new ComponentAssetPublisher($nextRegistry, app(Filesystem::class)))->publish($manifestRoot.'/public');
    locationCheck($nextPackage->publicDirectory() !== $package->publicDirectory() && is_file($releasePath.'/entry.js')
        && file_get_contents($releasePath.'/styles/component.css') === $original, 'Publishing the next release mutated the prior release');
    locationCheck(is_file($manifestRoot.'/public/'.$nextPackage->publicDirectory().'/styles/component.css'), 'Next release missing');
    file_put_contents($source, $original);
    file_put_contents($releasePath.'/entry.js', 'corrupt-public-output');
    $manifestReject(fn () => $publisher->publish($manifestRoot.'/public'), 'Corrupt immutable release was silently overwritten');
    file_put_contents($releasePath.'/entry.js', $contents['entry']);

    // Run an actual splitting build and the build adapter CLI in a separate
    // process, then consume/publish its manifest through the public registrar.
    mkdir($manifestRoot.'/esbuild-fixture');
    $process = proc_open(['node', __DIR__.'/esbuild-package-contract.mjs', $manifestRoot.'/esbuild-fixture'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    locationCheck(is_resource($process), 'Cannot launch the esbuild package contract');
    $buildOutput = stream_get_contents($pipes[1]);
    $buildError = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    locationCheck(proc_close($process) === 0 && $buildError === '', 'Esbuild package contract failed: '.$buildError);
    $builtEnvelope = json_decode($buildOutput, true, flags: JSON_THROW_ON_ERROR);
    $builtManifest = $compiler->load($builtEnvelope['manifest']);
    locationCheck($builtManifest->release === $builtEnvelope['release'] && count($builtManifest->artifacts) === 7, 'Actual split-build manifest incomplete');
    $builtPackage = new AssetPackage($builtManifest, $manifestRoot.'/esbuild-fixture/dist');
    $registrar->registerFamily([
        new ComponentRegistration(
            new ComponentDefinition('built-display', ComponentKind::Display, styles: ['built-style']), assetPackage: $builtPackage),
        new ComponentRegistration(
            new ComponentDefinition('built-field', ComponentKind::Field, stylesFrom: ['built-display'], entry: 'built-field', ssrScripts: ['built-ssr']), assetPackage: $builtPackage),
    ]);
    $builtEager = ComponentAssetPlan::for('built-field')->chunks;
    $builtLazy = $builtManifest->artifact($builtManifest->entries['built-field'])->dynamicImports;
    foreach ($builtLazy as $id) {
        locationCheck(! in_array($builtPackage->moduleIdentity($id).'.js', $builtEager, true), 'Actual lazy bundle became an eager preload');
    }
    $publisher->publish($manifestRoot.'/public');
    locationCheck(is_file($manifestRoot.'/public/'.$builtPackage->publicDirectory().'/worker.js'), 'Split build worker not published');

    $semanticPackage = new ComponentPackage($builtPackage, 'built', '1.2.3');
    $family = new ComponentFamily($semanticPackage, 'built-rating', [
        'field' => 'built-field', 'column' => 'built-column', 'entry' => 'built-entry',
    ], configurationVersion: 2, valueVersion: 3);
    $surfaceClass = ComponentSurface::class;
    $familyDefinitions = [
        new ComponentDefinition('built-display', ComponentKind::Primitive, styles: ['built-style']),
        new ComponentDefinition('built-field', ComponentKind::Field, stylesFrom: ['built-display'], entry: 'built-field',
            surface: new $surfaceClass($family, ComponentKind::Field, ScheduleField::class)),
        new ComponentDefinition('built-column', ComponentKind::Column, stylesFrom: ['built-display'],
            surface: new $surfaceClass($family, ComponentKind::Column, ScheduleColumn::class)),
        new ComponentDefinition('built-entry', ComponentKind::Entry, stylesFrom: ['built-display'],
            surface: new $surfaceClass($family, ComponentKind::Entry, ScheduleEntry::class)),
    ];
    $familyRegistry = ComponentRegistry::builtIn();
    $familyRegistrar = new ComponentRegistrar($familyRegistry);
    $familyRevision = $familyRegistry->revision();
    $manifestReject(fn () => $familyRegistrar->collectComponentFamily($family, array_slice($familyDefinitions, 0, 3)), 'Incomplete family collection accepted');
    locationCheck($familyRegistry->revision() === $familyRevision, 'Incomplete family collection changed live registry');
    $familyRegistrar->collectComponentFamily($family, $familyDefinitions);
    locationCheck(! $familyRegistry->has('built-field'), 'Pending semantic family leaked into registry');
    $familyRegistrar->completeCollection();
    $familyRegistry->freeze();
    $contracts = $familyRegistry->compiledGraph()->families->export();
    locationCheck(count($contracts['surfaces']) === 3 && $contracts['families']['built-rating']['configurationVersion'] === 2
        && $contracts['families']['built-rating']['valueVersion'] === 3 && $contracts['packages']['example/esbuild-component']['phpAbi'] === 1,
        'Semantic family lost independent versions/surfaces');
    locationCheck(! str_contains(json_encode($contracts, JSON_THROW_ON_ERROR), $manifestRoot), 'Private installed root leaked in semantic contract');
    locationCheck($familyRegistry->runtimeComponents('built-column') === ['built-column'] && $familyRegistry->stylesFor('built-entry') === ['built-style'],
        'Read-only family surface inherited editor runtime');
    locationCheck($familyRegistry->family('built-rating') === $family && $familyRegistry->surface('built-column')->kind === ComponentKind::Column,
        'Readonly family/surface lookup lost identity');
    $manifestReject(fn () => $familyRegistrar->collectComponentFamily($family, $familyDefinitions), 'Frozen semantic family mutated registry');
    $manifestReject(fn () => new ComponentPackage($builtPackage, 'built', '1.2.3', phpAbi: 2), 'Incompatible PHP ABI accepted');
    $manifestReject(fn () => new ComponentPackage($builtPackage, 'built', '1.2.3', jsAbi: 2), 'Incompatible JS ABI accepted');
    $manifestReject(fn () => new ComponentPackage($builtPackage, 'other', '1.2.3'), 'Foreign package roots admitted');
    foreach (['01.2.3', '1.2.3-01', '1.2.3-alpha..1', '1.2.3+bad_', 'dev-main'] as $version) {
        $manifestReject(fn () => new ComponentPackage($builtPackage, 'built', $version), 'Invalid semantic version admitted: '.$version);
    }
    foreach (['0.0.0', '1.2.3-alpha.1+001', '1.2.3-x-y.--+build--'] as $version) {
        locationCheck((new ComponentPackage($builtPackage, 'built', $version))->version === $version, 'Valid semantic version rejected: '.$version);
    }
    $manifestReject(fn () => new $surfaceClass($family, ComponentKind::Field, ScheduleColumn::class), 'Wrong renderer class kind accepted');
    $manifestReject(fn () => new $surfaceClass($family, ComponentKind::Field, SchemaContractAbstractField::class), 'Abstract renderer admitted');
    $manifestReject(fn () => new ComponentDefinition('built-wrong', ComponentKind::Field,
        surface: new $surfaceClass($family, ComponentKind::Field, ScheduleField::class)), 'Wrong surface identity admitted');
    $incompleteRegistry = new ComponentRegistry;
    $incompleteBindings = ['built-field' => $builtPackage];
    $manifestReject(fn () => $incompleteRegistry->registerBatch([$familyDefinitions[0], $familyDefinitions[1]], $incompleteBindings), 'Missing compiled family surfaces admitted');
    locationCheck($incompleteRegistry->revision() === 0 && ! $incompleteRegistry->has('built-field'), 'Family compilation failure changed registry');
    $otherPackage = new ComponentPackage($builtPackage, 'built', '2.0.0');
    $otherFamily = new ComponentFamily($otherPackage, 'built-other', ['field' => 'built-other-field']);
    $otherDefinition = new ComponentDefinition('built-other-field', ComponentKind::Field,
        surface: new $surfaceClass($otherFamily, ComponentKind::Field, ScheduleField::class));
    $index = [];
    $bindings = [];
    foreach ([...$familyDefinitions, $otherDefinition] as $definition) {
        $index[$definition->id] = $definition;
        $bindings[$definition->id] = $builtPackage;
    }
    $manifestReject(fn () => (new ComponentFamilyCompiler)->compile($index, $bindings), 'Conflicting semantic package versions admitted');
    $manifestReject(fn () => new ComponentFamily($semanticPackage, 'built-invalid', ['field' => 'built-field'], valueVersion: 0), 'Invalid value version admitted');
    $manifestReject(fn () => new ComponentFamily($semanticPackage, 'built-invalid', ['field' => 'built-field', 'column' => 'built-field']), 'Shared surface identity admitted');
    $foreignAssets = new AssetPackage(new AssetManifest('other/esbuild-component', $builtManifest->artifacts,
        $builtManifest->entries, $builtManifest->styles, $builtManifest->helpers), $manifestRoot.'/esbuild-fixture/dist');
    $foreignSemantic = new ComponentPackage($foreignAssets, 'built', '1.2.3');
    $foreignFamily = new ComponentFamily($foreignSemantic, 'built-foreign', ['field' => 'built-foreign-field']);
    $foreignDefinition = new ComponentDefinition('built-foreign-field', ComponentKind::Field,
        surface: new $surfaceClass($foreignFamily, ComponentKind::Field, ScheduleField::class));
    unset($index['built-other-field'], $bindings['built-other-field']);
    $index['built-foreign-field'] = $foreignDefinition;
    $bindings['built-foreign-field'] = $foreignAssets;
    $manifestReject(fn () => (new ComponentFamilyCompiler)->compile($index, $bindings), 'Overlapping author namespaces admitted');
    unset($index['built-foreign-field'], $bindings['built-foreign-field']);
    $index['built-hijacked'] = new ComponentDefinition('built-hijacked', ComponentKind::Display);
    $manifestReject(fn () => (new ComponentFamilyCompiler)->compile($index, $bindings), 'Unowned definition occupied semantic package namespace');
    echo 'PASS: typed package/family/surface contracts, independent ABI/config/value versions, renderer validation, atomic missing-surface rejection and freeze; read-only surfaces omit editor JS.'.PHP_EOL;

    // Optional, disposable browser harness of the actual compiled output. No
    // framework route, application configuration or private source is exposed.
    $browserRoot = getenv('FFF_PACKAGE_BROWSER_ROOT');
    if (is_string($browserRoot) && $browserRoot !== '') {
        $browserRoot = realpath($browserRoot);
        locationCheck($browserRoot !== false && is_dir($browserRoot), 'Browser harness needs an existing explicit output directory');
        $browserPrefix = 'fff-package-probe-'.bin2hex(random_bytes(6));
        $browserPublisher = new PackageReleasePublisher(app(Filesystem::class));
        $browserPublisher->preflight($builtPackage, $browserRoot, $browserPrefix);
        $browserPublisher->publish($builtPackage, $browserRoot, $browserPrefix);
        $path = $builtPackage->publicDirectory();
        $harness = '<!doctype html><html lang="en"><meta charset="utf-8"><title>FLEX split build probe</title>'
            .'<link rel="stylesheet" href="./'.$path.'/main.css"><script src="./'.$path.'/helper.js"></script>'
            .'<main class="esbuild-fixture"><button id="run">Load lazy module and worker</button><pre id="result">ready</pre></main>'
            .'<script type="module">import { run } from "./'.$path.'/main.js"; const output = document.querySelector("#result");'
            .'document.querySelector("#run").onclick = async () => { output.textContent = "running"; try {'
            .'const result = await run(); output.textContent = JSON.stringify({ ...result, ssr: globalThis.builtFixtureSsr, color: getComputedStyle(document.querySelector("main")).color });'
            .'} catch (error) { output.textContent = "FAILED: " + error; } };</script></html>';
        file_put_contents($browserRoot.'/'.$browserPrefix.'/index.html', $harness);
        echo 'BROWSER HARNESS: '.$browserRoot.'/'.$browserPrefix.'/index.html'.PHP_EOL;
    }
    echo 'PASS: actual esbuild split graph, adapter CLI, registrar integration and complete publication of static/lazy/CSS/worker/WASM/SSR outputs.'.PHP_EOL;
    $registry->freeze();
    $manifestReject(fn () => $registry->registerBatch([new ComponentDefinition('split-too-late', ComponentKind::Display)], ['split-too-late' => $package]), 'Frozen package binding accepted');

    foreach (['imports', 'dynamicImports', 'resources'] as $edge) {
        $invalid = $specification;
        $invalid['artifacts']['entry'][$edge][] = 'missing-artifact';
        $writeSpecification($invalid);
        $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Missing '.$edge.' dependency accepted');
    }
    foreach (['../private.js', '/private.js', 'chunks/../../private.js', 'chunks\\private.js', 'chunks/%2e%2e.js', 'chunks/CON.js', 'chunks/end./entry.js'] as $path) {
        $invalid = $specification;
        $invalid['artifacts']['shared']['path'] = $path;
        $writeSpecification($invalid);
        $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Unsafe artifact path accepted');
    }
    $invalid = $specification;
    $invalid['artifacts']['entry']['path'] = 'entry.php';
    $writeSpecification($invalid);
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Executable server artifact accepted');
    $invalid = $specification;
    $invalid['artifacts']['entry']['imports'] = ['style'];
    $writeSpecification($invalid);
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Wrong dependency kind accepted');
    $invalid = $specification;
    $invalid['artifacts']['unreachable'] = $draftArtifact('unreachable.js', 'module');
    $writeSpecification($invalid);
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Undeclared publication root accepted');
    $invalid = $specification;
    $invalid['artifacts']['lazy']['path'] = 'chunks/SHARED.js';
    $writeSpecification($invalid);
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Portable output collision accepted');
    $invalid = $specification;
    $invalid['helpers']['missing-helper'] = 'missing';
    $writeSpecification($invalid);
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Missing SSR helper accepted');
    $invalid = $specification;
    $invalid['entries']['script-as-module'] = 'helper';
    $writeSpecification($invalid);
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Classic script accepted as module entry');
    $invalid = $specification;
    $invalid['format'] = 999;
    $writeSpecification($invalid);
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Unknown manifest version accepted');
    $invalid = $specification;
    $invalid['url'] = 'https://example.invalid/entry.js';
    $writeSpecification($invalid);
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Unknown metadata key accepted');

    $writeSpecification($specification);
    unlink($manifestRoot.'/dist/workers/capture.wasm');
    $manifestReject(fn () => $compiler->verifySources($manifest, $manifestRoot.'/dist'), 'Missing worker WASM accepted');
    file_put_contents($manifestRoot.'/outside.wasm', 'outside');
    symlink($manifestRoot.'/outside.wasm', $manifestRoot.'/dist/workers/capture.wasm');
    $manifestReject(fn () => $compiler->build($specificationPath, $manifestRoot.'/dist'), 'Source symlink escape accepted');

    file_put_contents($manifestRoot.'/invalid.json', str_repeat(' ', 4194305));
    $manifestReject(fn () => $compiler->load($manifestRoot.'/invalid.json'), 'Oversized manifest accepted');
    $tampered = json_decode($manifest->toJson(), true, flags: JSON_THROW_ON_ERROR);
    $tampered['artifacts']['entry']['bytes']++;
    file_put_contents($manifestRoot.'/invalid.json', json_encode($tampered, JSON_THROW_ON_ERROR));
    $manifestReject(fn () => $compiler->load($manifestRoot.'/invalid.json'), 'Tampered release metadata accepted');

    // Deep native ESM graphs remain iterative, without stack-depth recursion.
    $manifestReject(fn () => new AssetManifest('con/reserved', $manifest->artifacts, $manifest->entries, $manifest->styles, $manifest->helpers), 'Nonportable package directory accepted');
    $manifestReject(fn () => new AssetManifest('example/collision', [
        'parent' => new AssetArtifact('parent.js', ArtifactKind::Module, str_repeat('a', 64), 1, ['child']),
        'child' => new AssetArtifact('parent.js/child.js', ArtifactKind::Module, str_repeat('a', 64), 1),
    ], ['collision' => 'parent']), 'File-directory collision accepted');
    $manifestReject(fn () => new AssetManifest('example/collision', [
        'parent' => new AssetArtifact('Chunks/parent.js', ArtifactKind::Module, str_repeat('a', 64), 1, ['child']),
        'child' => new AssetArtifact('chunks/child.js', ArtifactKind::Module, str_repeat('a', 64), 1),
    ], ['collision' => 'parent']), 'Directory-case collision accepted');
    $artifacts = [];
    for ($index = 0; $index < 1024; $index++) {
        $artifacts['node-'.$index] = new AssetArtifact('node-'.$index.'.js', ArtifactKind::Module, str_repeat('a', 64), 1,
            $index < 1023 ? ['node-'.($index + 1)] : ['node-0']);
    }
    $deep = new AssetManifest('example/deep', $artifacts, ['deep-field' => 'node-0']);
    locationCheck(count($deep->eagerArtifactsFor('deep-field')) === 1024, 'Deep/cyclic native ESM graph did not terminate');
    $budget = new PackageAssetBudget;
    $manyPackages = [];
    for ($index = 0; $index < 129; $index++) {
        $manyPackages['component-'.$index] = new AssetPackage(new AssetManifest('example/budget-'.$index,
            ['entry' => new AssetArtifact('entry.js', ArtifactKind::Module, str_repeat('a', 64), 1)], ['entry' => 'entry']), $manifestRoot.'/dist');
    }
    $budget->validate(array_slice($manyPackages, 0, 128, true));
    $manifestReject(fn () => $budget->validate($manyPackages), 'Aggregate package budget ignored');
    $repeated = array_fill(0, 512, $manyPackages['component-0']);
    $budget->validate(array_combine(array_map(static fn ($key) => 'component-'.$key, array_keys($repeated)), $repeated));
    $largeArtifacts = [];
    for ($index = 0; $index < 4096; $index++) {
        $largeArtifacts['node-'.$index] = new AssetArtifact('node-'.$index.'.js', ArtifactKind::Module, str_repeat('a', 64), 1,
            $index < 4095 ? ['node-'.($index + 1)] : []);
    }
    $largePackages = [];
    for ($index = 0; $index < 5; $index++) {
        $largePackages['component-'.$index] = new AssetPackage(new AssetManifest('example/large-'.$index,
            $largeArtifacts, ['entry' => 'node-0']), $manifestRoot.'/dist');
    }
    $budget->validate(array_slice($largePackages, 0, 4, true));
    $manifestReject(fn () => $budget->validate($largePackages), 'Aggregate artifact budget ignored');
    $denseArtifacts = [];
    for ($index = 0; $index < 1024; $index++) {
        $dependencies = [];
        for ($offset = 1; $offset <= 32; $offset++) {
            $dependencies[] = 'node-'.(($index + $offset) % 1024);
        }
        $denseArtifacts['node-'.$index] = new AssetArtifact('node-'.$index.'.js', ArtifactKind::Module, str_repeat('a', 64), 1, $dependencies);
    }
    $densePackages = [];
    for ($index = 0; $index < 5; $index++) {
        $densePackages['component-'.$index] = new AssetPackage(new AssetManifest('example/dense-'.$index,
            $denseArtifacts, ['entry' => 'node-0']), $manifestRoot.'/dist');
    }
    $budget->validate(array_slice($densePackages, 0, 4, true));
    $manifestReject(fn () => $budget->validate($densePackages), 'Aggregate edge budget ignored');
    try {
        (new PackageAssetCompiler)->compile([], $largePackages, []);
        throw new RuntimeException('Unbounded package traversal admitted');
    } catch (InvalidArgumentException $error) {
        locationCheck(str_contains($error->getMessage(), 'aggregate asset metadata budget'), 'Aggregate check did not precede filesystem/root traversal');
    }
    echo 'PASS: aggregate package/artifact/edge limits with exact boundaries and reused-package deduplication before filesystem traversal.'.PHP_EOL;
    echo "PASS: portable manifest build/load, deterministic content identity, static/lazy/worker/CSS boundaries, typed references, source containment, strict budgets and deep native ESM graphs. Both immutable package releases coexist.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getTraceAsString().PHP_EOL);
    throw $error;
} finally {
    app(Filesystem::class)->deleteDirectory($manifestRoot);
    app(Filesystem::class)->deleteDirectory($directory);
}

$dependency = 'reference-b';
$capability = 'reference-b';
$referenceRegistry = new ComponentRegistry;
$referenceRegistry->register(new ComponentDefinition('reference-b', ComponentKind::Field));
$referenceRegistry->register(new ComponentDefinition('reference-c', ComponentKind::Field));
$referenceRegistry->register(new ComponentDefinition('reference-a', ComponentKind::Field, uses: [&$dependency], capabilities: ['editor' => [&$capability]]));
$referenceRegistry->freeze();
$dependency = 'reference-c';
$capability = 'missing';
locationCheck($referenceRegistry->definition('reference-a')->uses === ['reference-b'], 'Frozen definition retained dependency references.');
locationCheck($referenceRegistry->rootsFor('reference-a', ['editor']) === ['reference-a', 'reference-b'], 'Frozen capability retained references.');
$target = 'one';
$lazy = 'two';
$artifacts = [
    'one' => new AssetArtifact('one.js', ArtifactKind::Module, str_repeat('a', 64), 1, dynamicImports: [&$lazy]),
    'two' => new AssetArtifact('two.js', ArtifactKind::Module, str_repeat('b', 64), 1),
];
$referenceManifest = new AssetManifest('reference/package', $artifacts, entries: ['reference-main' => &$target]);
$surfaceReference = 'reference-field';
$referencePackage = new ComponentPackage(new AssetPackage($referenceManifest, __DIR__), 'reference', '1.0.0');
$referenceFamily = new ComponentFamily($referencePackage, 'reference-family', ['field' => &$surfaceReference]);
$surfaceReference = 'reference-injected';
locationCheck($referenceFamily->surfaces === ['field' => 'reference-field'], 'Family surface map retained caller references.');
$before = $referenceManifest->toJson();
$seal = $referenceManifest->release;
$target = 'two';
$lazy = 'missing';
locationCheck($referenceManifest->toJson() === $before && $referenceManifest->release === $seal, 'Manifest payload drifted under unchanged release.');
$largeRegistry = new ComponentRegistry;
for ($node = 0; $node < 8192; $node++) {
    $largeRegistry->register(new ComponentDefinition('scale-'.$node, ComponentKind::Field, uses: $node ? ['scale-'.($node - 1)] : []));
}
$largeGraph = $largeRegistry->freeze();
locationCheck(count($largeGraph->runtimeComponents('scale-8191')) === 8192, 'Valid long graph lost dependencies.');
locationCheck($largeGraph->stylesFor('scale-8191') === [], 'Empty graph styles changed.');
$largeRegistry->runtimeComponents('scale-8191');
try {
    $overLimit = new ComponentGraphCompiler;
    $overLimit->compile(array_fill_keys(range(0, 8192), new ComponentDefinition('oversized', ComponentKind::Field)));
    throw new RuntimeException('Oversized graph admitted.');
} catch (InvalidArgumentException) {
}
echo "PASS: immutable graph/manifest reference snapshots; bounded 8192-node iterative graph.\n";

$pairedDefinitions = [];
for ($node = 0; $node < 600; $node++) {
    $id = 'paired-'.$node;
    $pairedDefinitions[$id] = new ComponentDefinition($id, ComponentKind::Field, styles: [$id], uses: $node ? ['paired-'.($node - 1)] : []);
}
$paired = new ComponentProjections($pairedDefinitions);
$cacheProperty = new ReflectionProperty($paired, 'cache');
$memberProperty = new ReflectionProperty($paired, 'members');
for ($node = 0; $node < 130; $node++) {
    $paired->stylesFor('paired-'.$node);
    $paired->runtimeComponents('paired-'.$node);
}
$pairedCache = $cacheProperty->getValue($paired);
locationCheck(count($pairedCache) === 130, 'Paired projections evicted each other before the component limit.');
foreach ($pairedCache as $entry) {
    locationCheck(isset($entry['styles'], $entry['runtime']), 'Warm component lost one projection kind.');
}
for ($node = 130; $node < 600; $node++) {
    locationCheck(count($paired->stylesFor('paired-'.$node)) === $node + 1, 'Styles changed under eviction.');
    locationCheck(count($paired->runtimeComponents('paired-'.$node)) === $node + 1, 'Runtime changed under eviction.');
    $retained = $cacheProperty->getValue($paired);
    $members = 0;
    foreach ($retained as $entry) {
        foreach ($entry as $projection) {
            $members += count($projection);
        }
    }
    locationCheck(count($retained) <= 256 && $members <= 65536 && $members === $memberProperty->getValue($paired), 'Paired projection cache escaped a limit or miscounted evicted members.');
}
locationCheck($paired->runtimeComponents('paired-0') === ['paired-0'], 'Evicted projection did not recompute.');
echo "PASS: 130 paired warm components retain both projections; 600-component eviction preserves ordering, 256-root and aggregate 65536-member limits.\n";
