<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\AlpineBuildManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\AssetBuildValidator;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\BuildMetadataReader;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetArtifact;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\AssetRegistration;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\ConsoleIntegration;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function buildCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function buildReject(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}
function buildSeal(string $root, array $assets, array $metadata): array
{
    ksort($assets);
    ksort($metadata);
    $bytes = [];
    foreach ($assets as $file => $digest) {
        $bytes[$file] = filesize($root.'/resources/dist/'.$file);
    }
    $release = hash('sha256', json_encode(['assets' => $assets, 'bytes' => $bytes, 'metadata' => $metadata], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $seal = ['format' => 2, 'release' => $release, 'assets' => $assets, 'bytes' => $bytes, 'metadata' => $metadata];
    file_put_contents($root.'/resources/dist/asset-versions.json', json_encode($seal, JSON_THROW_ON_ERROR));

    return $seal;
}

$root = dirname(__DIR__, 2);
$filesystem = new Filesystem;
$directory = sys_get_temp_dir().'/fff-build-preflight-'.bin2hex(random_bytes(8));
$filesystem->ensureDirectoryExists($directory.'/resources/dist/components');
$filesystem->ensureDirectoryExists($directory.'/src/Support/Assets');
$groups = 0;
try {
    $validator = new AssetBuildValidator;
    $real = $validator->validate();
    buildCheck($real->errorCount === 0 && $real->artifacts > 200 && $real->release !== null, 'Current build does not pass strict output parity');
    $groups++;

    $sealPath = $directory.'/resources/dist/asset-versions.json';
    buildReject(fn () => (new AssetVersionManifest($directory, false))->assets(), 'Missing seal silently accepted');
    foreach (['{', '[]', '{"format":999,"assets":{}}', str_repeat(' ', BuildMetadataReader::MAX_BYTES + 1)] as $invalid) {
        file_put_contents($sealPath, $invalid);
        buildReject(fn () => (new AssetVersionManifest($directory, false))->assets(), 'Invalid seal silently accepted');
    }
    $groups++;

    $module = $directory.'/resources/dist/components/probe.js';
    file_put_contents($module, 'export default 1');
    $alpinePath = $directory.'/resources/dist/components/alpine-manifest.json';
    $alpine = ['probe' => [], '__format__' => 2, '__entries__' => ['probe'], '__shared_chunks__' => [],
        '__chunk_imports__' => ['probe.js' => []], '__dynamic_imports__' => ['probe.js' => []], '__chunk_modules__' => []];
    file_put_contents($alpinePath, json_encode($alpine));
    $phpPath = $directory.'/src/Support/Assets/BuiltInComponentIndex.php';
    copy($root.'/src/Support/Assets/BuiltInComponentIndex.php', $phpPath);
    $assets = ['components/probe.js' => hash_file('sha256', $module)];
    $metadata = ['resources/dist/components/alpine-manifest.json' => hash_file('sha256', $alpinePath),
        'src/Support/Assets/BuiltInComponentIndex.php' => hash_file('sha256', $phpPath)];
    $valid = buildSeal($directory, $assets, $metadata);
    $manifest = new AssetVersionManifest($directory, false);
    buildCheck($manifest->release() === $valid['release'] && $manifest->bytesFor('components/probe.js') === 16, 'Seal cannot be read deterministically');
    $previousTime = filemtime($module);
    file_put_contents($module, 'export default 2');
    touch($module, $previousTime);
    buildCheck(array_filter($validator->validate(packageRoot: $directory)->errors, fn ($error) => $error['code'] === 'artifact-parity') !== [], 'Same-size same-mtime hash corruption accepted');
    $second = buildSeal($directory, ['components/probe.js' => hash_file('sha256', $module)], $metadata);
    buildCheck($second['release'] !== $valid['release'], 'Changed same-size output retained release identity');
    // Existing scoped snapshot remains immutable; a fresh scope reads the new seal.
    buildCheck($manifest->release() === $valid['release'] && (new AssetVersionManifest($directory, false))->release() === $second['release'], 'Build snapshots leaked across scopes');
    unlink($module);
    buildCheck(array_filter($validator->validate(packageRoot: $directory)->errors, fn ($error) => $error['code'] === 'artifact-parity') !== [], 'Missing sealed entry accepted');
    $groups++;

    $metadata['resources/dist/components/alpine-manifest.json'] = str_repeat('a', 64);
    file_put_contents($module, 'export default 2');
    buildSeal($directory, ['components/probe.js' => hash_file('sha256', $module)], $metadata);
    buildCheck(array_filter($validator->validate(packageRoot: $directory)->errors, fn ($error) => $error['code'] === 'metadata-parity') !== [], 'Mixed PHP/JS release accepted');
    $corrupt = $second;
    $corrupt['bytes']['components/probe.js']++;
    file_put_contents($sealPath, json_encode($corrupt));
    buildReject(fn () => (new AssetVersionManifest($directory, false))->assets(), 'Forged byte-size seal accepted');
    $corrupt['assets']['../outside.js'] = str_repeat('a', 64);
    file_put_contents($sealPath, json_encode($corrupt));
    buildReject(fn () => (new AssetVersionManifest($directory, false))->assets(), 'Path traversal accepted');
    $groups++;

    $original = (new AlpineBuildManifest($alpinePath))->data();
    foreach ([
        array_replace($alpine, ['__format__' => 1]),
        array_replace($alpine, ['__entries__' => ['../probe']]),
        array_replace($alpine, ['__shared_chunks__' => ['probe.js']]),
        array_replace($alpine, ['__dynamic_imports__' => ['probe.js' => ['missing.js']]]),
        array_replace($alpine, ['__chunk_imports__' => []]),
        array_replace($alpine, ['unknown' => []]),
    ] as $invalid) {
        file_put_contents($alpinePath, json_encode($invalid));
        buildReject(fn () => (new AlpineBuildManifest($alpinePath))->data(), 'Malformed Alpine graph accepted');
    }
    file_put_contents($alpinePath, json_encode($original));
    $snapshot = new AlpineBuildManifest($alpinePath);
    $snapshot->data();
    file_put_contents($alpinePath, '{}');
    buildCheck($snapshot->data() === $original, 'Alpine immutable snapshot mutated');
    buildReject(fn () => (new AlpineBuildManifest($alpinePath))->data(), 'Alpine corruption hidden in a new scope');
    $groups++;

    file_put_contents($directory.'/external.js', 'export default 1');
    $package = new AssetPackage(new AssetManifest('example/preflight', [
        'entry' => new AssetArtifact('external.js', ArtifactKind::Module, hash('sha256', 'export default 1'), 16),
    ], entries: ['probe' => 'entry']), $directory);
    $registry = ComponentRegistry::builtIn();
    $registrar = new ComponentRegistrar($registry);
    $registrar->collectPackage($package, [new ComponentDefinition('preflight-probe', ComponentKind::Field, entry: 'probe')]);
    $registrar->completeCollection();
    $registry->freeze();
    buildCheck($validator->validate($registry)->errorCount === 0, 'Valid external package rejected');
    file_put_contents($directory.'/external.js', 'export default 2');
    $bad = $validator->validate($registry);
    buildCheck($bad->packages === 1 && array_filter($bad->errors, fn ($error) => $error['code'] === 'package-parity') !== [], 'Corrupt external package not included in doctor');
    $public = $directory.'/public';
    mkdir($public);
    file_put_contents($public.'/sentinel', 'unchanged');
    buildReject(fn () => (new ComponentAssetPublisher($registry, $filesystem))->publish($public), 'Publisher activated corrupt package');
    buildCheck(scandir($public) === ['.', '..', 'sentinel'], 'Failed preflight wrote publication state');
    $groups++;

    $dispatcher = new Dispatcher($app);
    $app->instance(DispatcherContract::class, $dispatcher);
    $app->instance(ComponentRegistry::class, $registry);
    $started = false;
    ConsoleIntegration::register($app, function () use (&$started): void {
        $started = true;
    });
    buildReject(fn () => $dispatcher->dispatch(new CommandStarting('filament:assets', new ArrayInput([]), new NullOutput)), 'Native Filament publication not gated before its own copies');
    buildCheck(! $started && scandir($public) === ['.', '..', 'sentinel'], 'Native preflight caused writes');
    $groups++;

    $app['config']->set('filament-flex-fields.assets.development_fallback', false);
    buildReject(fn () => (new AssetRegistration($directory.'/empty'))->alpineComponents(), 'Missing declared entry silently skipped');
    buildReject(fn () => (new AssetRegistration($directory.'/empty'))->scripts(), 'Missing declared bootstrap silently registered');
    $app['config']->set('filament-flex-fields.assets.development_fallback', true);
    $app['env'] = 'local';
    file_put_contents($sealPath, '{}');
    buildCheck((new AssetVersionManifest($directory))->assets() === [], 'Explicit local fallback rejected');
    buildReject(fn () => (new AssetVersionManifest($directory, false))->assets(), 'Strict deployment reader used development fallback');
    $app['env'] = 'production';
    buildReject(fn () => (new AssetVersionManifest($directory))->assets(), 'Production accepted local fallback flag');
    $app['env'] = 'testing';
    $app['config']->set('filament-flex-fields.assets.development_fallback', false);
    file_put_contents($sealPath, json_encode($second));
    file_put_contents($directory.'/resources/dist/components/unsealed.js', 'export default 3');
    buildReject(fn () => (new AssetVersionManifest($directory, false))->versionFor($directory.'/resources/dist/components/unsealed.js'), 'Unsealed distribution asset used mtime fallback');
    $groups++;

    $link = $directory.'/resources/dist/components/link.js';
    symlink($module, $link);
    buildSeal($directory, ['components/link.js' => hash_file('sha256', $link)], $second['metadata']);
    buildCheck(array_filter($validator->validate(packageRoot: $directory)->errors, fn ($error) => $error['code'] === 'artifact-parity') !== [], 'Symlink artifact accepted');
    $groups++;

    $missing = [];
    for ($index = 0; $index < 200; $index++) {
        $file = 'components/missing-'.$index.'.js';
        file_put_contents($directory.'/resources/dist/'.$file, 'x');
        $missing[$file] = hash('sha256', 'x');
    }
    buildSeal($directory, $missing, $second['metadata']);
    foreach (array_keys($missing) as $file) {
        unlink($directory.'/resources/dist/'.$file);
    }
    $bounded = $validator->validate(packageRoot: $directory);
    buildCheck($bounded->errorCount >= 200 && count($bounded->errors) === 128, 'Doctor failed to bound error history while preserving total count');
    $groups++;

    echo json_encode(['groups' => $groups, 'realArtifacts' => $real->artifacts, 'valid' => true], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $filesystem->deleteDirectory($directory);
}
