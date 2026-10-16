<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\Assets\FlexFieldsAlpineComponent;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsCss;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsJs;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetArtifact;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\CoreReleaseRetention;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PackageReleasePublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PackageReleaseRetention;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PublicationLock;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\ReleaseDirectoryPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\ReleaseInventory;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

function releaseCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function releaseReject(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}
function releaseFixture(string $root, string $version): BuiltInAssetRelease
{
    $files = [
        'components/entry.js' => 'export const version='.json_encode($version).'; export const lazy=()=>import("./lazy.js");',
        'components/lazy.js' => 'export const version='.json_encode($version).';',
        'css/core.css' => ':root{--release:'.json_encode($version).'}',
        'audio/tone.mp3' => 'binary-'.$version,
    ];
    $filesystem = new Filesystem;
    $assets = [];
    $bytes = [];
    foreach ($files as $file => $content) {
        $filesystem->ensureDirectoryExists(dirname($root.'/resources/dist/'.$file));
        file_put_contents($root.'/resources/dist/'.$file, $content);
        $assets[$file] = hash('sha256', $content);
        $bytes[$file] = strlen($content);
    }
    ksort($assets);
    ksort($bytes);
    $metadata = ['resources/dist/components/alpine-manifest.json' => str_repeat('a', 64), 'src/Support/Assets/BuiltInComponentIndex.php' => str_repeat('b', 64)];
    $release = hash('sha256', json_encode(['assets' => $assets, 'bytes' => $bytes, 'metadata' => $metadata], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    file_put_contents($root.'/resources/dist/asset-versions.json', json_encode(['format' => 2, 'release' => $release, 'assets' => $assets, 'bytes' => $bytes, 'metadata' => $metadata], JSON_THROW_ON_ERROR));

    return new BuiltInAssetRelease(new AssetVersionManifest($root, false));
}

function portableReleaseFixture(string $root, string $version, string $name = 'acme/author'): AssetPackage
{
    $filesystem = new Filesystem;
    $files = [
        'entry' => ['entry.js', ArtifactKind::Module, 'export const version='.json_encode($version).';export const lazy=()=>import("./lazy.js");'],
        'lazy' => ['lazy.js', ArtifactKind::Module, 'export const version='.json_encode($version).';'],
        'style' => ['control.css', ArtifactKind::Stylesheet, '.author-control{--release:'.json_encode($version).'}'],
        'audio' => ['tone.mp3', ArtifactKind::Audio, 'binary-'.$version],
    ];
    $filesystem->ensureDirectoryExists($root);
    $artifacts = [];
    foreach ($files as $id => [$path, $type, $content]) {
        file_put_contents($root.'/'.$path, $content);
        $artifacts[$id] = new AssetArtifact($path, $type, hash('sha256', $content), strlen($content),
            dynamicImports: $id === 'entry' ? ['lazy'] : [], resources: $id === 'entry' ? ['audio'] : []);
    }

    return new AssetPackage(new AssetManifest($name, $artifacts, ['author-entry' => 'entry'], ['author-style' => 'style']), $root);
}

if (($argv[1] ?? null) === '--publish-release') {
    $release = new BuiltInAssetRelease(new AssetVersionManifest($argv[2], false));
    $result = PublicationLock::run($argv[3], fn (string $root): array => (new ReleaseDirectoryPublisher(new Filesystem))->publish($release->inventory(), $root, $release->publicDirectory('deployment')));
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(0);
}

if (($argv[1] ?? null) === '--kill-stage') {
    $source = $argv[2];
    $public = $argv[3];
    $ready = $argv[4];
    $release = new BuiltInAssetRelease(new AssetVersionManifest($source, false));
    $filesystem = new class($ready) extends Filesystem
    {
        public function __construct(private readonly string $ready) {}

        public function copy($path, $target): bool
        {
            $result = parent::copy($path, $target);
            file_put_contents($this->ready, 'staged');
            while (true) {
                usleep(10000);
            }
        }
    };
    PublicationLock::run($public, fn (string $root): array => (new ReleaseDirectoryPublisher($filesystem))->publish($release->inventory(), $root, $release->publicDirectory('deployment')));
    exit(1);
}

$work = sys_get_temp_dir().'/fff-core-release-'.bin2hex(random_bytes(8));
$filesystem = new Filesystem;
$filesystem->ensureDirectoryExists($work.'/public');
$public = $work.'/public';
$prefix = 'deployment';
$proofs = [];
$preserve = false;
try {
    $publisher = new ReleaseDirectoryPublisher($filesystem);
    $a = releaseFixture($work.'/a', 'A');
    $b = releaseFixture($work.'/b', 'B');
    $c = releaseFixture($work.'/c', 'C');
    $d = releaseFixture($work.'/d', 'D');
    $publish = static fn (BuiltInAssetRelease $release): array => PublicationLock::run($public, static function (string $root) use ($release, $publisher, $prefix): array {
        $publisher->preflight($release->inventory(), $root, $release->publicDirectory($prefix));

        return $publisher->publish($release->inventory(), $root, $release->publicDirectory($prefix));
    });
    releaseCheck($publish($a) === ['copied' => 4, 'unchanged' => 0] && $publish($a) === ['copied' => 0, 'unchanged' => 4], 'Release commit is not idempotent.');
    $before = file_get_contents($public.'/'.$a->publicDirectory($prefix).'/components/entry.js');
    $publish($b);
    releaseCheck(file_get_contents($public.'/'.$a->publicDirectory($prefix).'/components/entry.js') === $before, 'Rollout overwrote old entry bytes.');
    releaseCheck($a->publicDirectory($prefix) !== $b->publicDirectory($prefix), 'Different releases share URLs.');
    $publisher->verify($a->inventory(), $public.'/'.$a->publicDirectory($prefix));
    $proofs[] = 'Complete A and B coexist; idempotent retry and rollback to A preserve all original bytes and import paths.';

    $broken = new class extends Filesystem
    {
        private int $copies = 0;

        public function copy($path, $target): bool
        {
            return ++$this->copies !== 2 && parent::copy($path, $target);
        }
    };
    releaseReject(fn () => PublicationLock::run($public, fn (string $root): array => (new ReleaseDirectoryPublisher($broken))->publish($c->inventory(), $root, $c->publicDirectory($prefix))), 'Partial release copy succeeded.');
    releaseCheck(! file_exists($public.'/'.$c->publicDirectory($prefix)), 'Failed copy exposed a partial final release.');
    releaseCheck(glob(dirname($public.'/'.$c->publicDirectory($prefix)).'/.fff-stage-*') === [], 'Caught failure left a stage.');
    $proofs[] = 'A copy failure exposes no final directory or commit marker; stage is cleaned and existing releases remain intact.';

    $ready = $work.'/child-ready';
    $process = proc_open([PHP_BINARY, __FILE__, '--kill-stage', $work.'/c', $public, $ready], [0 => ['pipe', 'r'], 1 => ['file', $work.'/child.log', 'a'], 2 => ['file', $work.'/child.log', 'a']], $pipes);
    releaseCheck(is_resource($process), 'Could not start abrupt-death publisher.');
    fclose($pipes[0]);
    try {
        $deadline = microtime(true) + 10;
        while (! is_file($ready) && microtime(true) < $deadline && proc_get_status($process)['running']) {
            usleep(10000);
        }
        $staged = is_file($ready);
        if ($staged) {
            $started = hrtime(true);
            $entered = false;
            releaseReject(function () use ($public, &$entered): void {
                PublicationLock::run($public, function () use (&$entered): void {
                    $entered = true;
                }, 50);
            }, 'A second owner entered a live writer lock.');
            releaseCheck(! $entered && (hrtime(true) - $started) < 1000000000, 'Lock contention did not fail within the bounded wait.');
        }
    } finally {
        proc_terminate($process, 9);
        proc_close($process);
    }
    releaseCheck($staged, 'Child failed before staging: '.file_get_contents($work.'/child.log'));
    releaseCheck(! file_exists($public.'/'.$c->publicDirectory($prefix)), 'Killed writer activated an incomplete release.');
    releaseCheck(count(glob(dirname($public.'/'.$c->publicDirectory($prefix)).'/.fff-stage-*')) === 1, 'Abrupt death did not exercise abandoned stage recovery.');
    $outside = $work.'/outside';
    file_put_contents($outside, 'preserve');
    $stage = glob(dirname($public.'/'.$c->publicDirectory($prefix)).'/.fff-stage-*')[0];
    symlink($outside, $stage.'/outside-link');
    $publish($c);
    releaseCheck(file_get_contents($outside) === 'preserve' && glob(dirname($public.'/'.$c->publicDirectory($prefix)).'/.fff-stage-*') === [], 'Recovery followed a symlink or leaked the stage.');
    $proofs[] = 'A live writer causes bounded lock timeout without entering; SIGKILL during staging releases the root lock; retry removes the abandoned stage without following symlinks and commits the complete release.';

    $target = $public.'/'.$b->publicDirectory($prefix).'/components/lazy.js';
    $valid = file_get_contents($target);
    file_put_contents($target, 'tampered');
    releaseReject(fn () => $publish($b), 'Immutable corrupt release was silently repaired.');
    releaseCheck(file_get_contents($target) === 'tampered', 'A corrupt final release was overwritten.');
    file_put_contents($target, $valid);
    $proofs[] = 'Tampered immutable release rejects publication without overwrite; deployment integrity remains explicit.';

    $publish($d);
    $hash = static fn (BuiltInAssetRelease $release): string => basename($release->publicDirectory());
    $timestamps = [[$a, time() - 40 * 86400], [$b, time() - 35 * 86400], [$c, time() - 86400], [$d, time()]];
    foreach ($timestamps as [$release, $timestamp]) {
        touch($public.'/'.$release->publicDirectory($prefix).'/.release.json', $timestamp);
    }
    $retention = new CoreReleaseRetention($filesystem);
    $rows = $retention->run($public, $prefix, $hash($a), 1, 30 * 86400, [$hash($c)]);
    releaseCheck(array_column(array_filter($rows, fn (array $row): bool => $row['eligible']), 'release') === [$hash($b)], 'Retention did not protect current, newest, grace-period and explicit node releases.');
    releaseCheck(is_dir($public.'/'.$b->publicDirectory($prefix)), 'Preview deleted an old release.');
    $retention->run($public, $prefix, $hash($a), 1, 30 * 86400, [$hash($c)], true);
    releaseCheck(! is_dir($public.'/'.$b->publicDirectory($prefix)) && is_dir($public.'/'.$a->publicDirectory($prefix)), 'Explicit retention removed the wrong release.');
    $publish($b);
    $proofs[] = 'Retention preview does not change release artifacts; explicit prune protects current/other nodes/newest/grace period and a pruned release can be restored from its sealed sources.';

    $native = new BuiltInAssetRelease(new AssetVersionManifest(allowDevelopmentFallback: false));
    $nativeRoot = $work.'/native';
    $filesystem->ensureDirectoryExists($nativeRoot);
    $result = (new ComponentAssetPublisher(app(ComponentRegistry::class), $filesystem))->publish($nativeRoot);
    $publisher->verify($native->inventory(), $nativeRoot.'/'.$native->publicDirectory());
    releaseCheck($result['copied'] >= count($native->inventory()->files), 'Native publisher omitted the builtin snapshot.');
    foreach ([...FilamentAsset::getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]), ...FilamentAsset::getScripts([FilamentFlexFieldsPlugin::PACKAGE_NAME]), ...FilamentAsset::getAlpineComponents([FilamentFlexFieldsPlugin::PACKAGE_NAME])] as $asset) {
        if (! $asset instanceof FlexFieldsCss && ! $asset instanceof FlexFieldsJs && ! $asset instanceof FlexFieldsAlpineComponent) {
            continue;
        }
        $relative = $native->publicPath($asset->getPath());
        if ($relative === null) {
            continue;
        }
        releaseCheck($asset->isRemote() && $asset->getRelativePublicPath() === $relative && is_file($nativeRoot.'/'.$relative), 'Native registration may copy or address an incomplete release.');
        $url = $asset instanceof FlexFieldsCss ? $asset->getHref() : $asset->getSrc();
        releaseCheck(str_ends_with($url, $relative) && ! str_contains($url, '?v='), 'Registered module identity differs from immutable graph URL.');
    }
    $proofs[] = 'Actual native registry publishes the entire sealed distribution; CSS/core/entries/chunks/SSR helpers address one immutable tree without duplicate native copies.';
    releaseCheck(Artisan::call('fff:assets:doctor', ['--published' => true, '--public-root' => $nativeRoot, '--json' => true]) === 0, 'Published doctor rejected the native release.');
    releaseCheck(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['valid'] === true, 'Published doctor did not report verified public copies.');
    $nativeFile = $nativeRoot.'/'.$native->publicDirectory().'/css/core.css';
    $nativeValid = file_get_contents($nativeFile);
    file_put_contents($nativeFile, 'changed');
    releaseCheck(Artisan::call('fff:assets:doctor', ['--published' => true, '--public-root' => $nativeRoot, '--json' => true]) === 1, 'Published doctor accepted modified public bytes.');
    $diagnosticOutput = Artisan::output();
    $report = json_decode($diagnosticOutput, true, flags: JSON_THROW_ON_ERROR);
    releaseCheck($report['errors'][0]['code'] === 'published_release_invalid' && ! str_contains($diagnosticOutput, $nativeRoot), 'Published diagnostics exposed private paths or wrong failure code.');
    file_put_contents($nativeFile, $nativeValid);
    releaseCheck(Artisan::call('fff:assets:releases', ['--public-root' => $nativeRoot, '--json' => true]) === 0, 'Native retention CLI failed.');
    $rows = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['releases'];
    releaseCheck(count($rows) === 1 && $rows[0]['protected'] === true && $rows[0]['removed'] === false, 'CLI preview did not protect current release.');
    releaseCheck(Artisan::call('fff:assets:releases', ['--public-root' => $nativeRoot, '--keep' => 'invalid', '--json' => true]) === 1, 'Malformed retention CLI option accepted.');
    $proofs[] = 'Native CLI verifies public copies, rejects altered bytes with redacted machine-readable diagnostics, previews protected history and rejects malformed retention options.';

    releaseReject(fn () => $retention->run($public, '../escape', $hash($a)), 'Unsafe prefix accepted.');
    releaseReject(fn () => $retention->run($public, $prefix, $hash($a), 0), 'Zero keep accepted.');
    releaseReject(fn () => $retention->run($public, $prefix, $hash($a), 1, 0), 'Zero grace accepted.');
    releaseReject(fn () => $a->publicPath($work.'/a/resources/dist/components/entry.js', '../escape'), 'Unsafe URL prefix or missing source escaped validation.');
    releaseReject(fn () => new ReleaseInventory($work.'/a/resources/dist', ['executable.php' => ['sha256' => str_repeat('a', 64), 'bytes' => 1]], '{}'), 'Executable server file accepted for public distribution.');
    $badRoot = $work.'/bad-public';
    $filesystem->ensureDirectoryExists($badRoot.'/filament-flex-fields-assets/.publish.lock');
    releaseReject(fn () => PublicationLock::run($badRoot, static fn (): bool => true), 'Directory masquerading as lock file accepted.');
    rmdir($badRoot.'/filament-flex-fields-assets/.publish.lock');
    if (function_exists('posix_mkfifo')) {
        releaseCheck(posix_mkfifo($badRoot.'/filament-flex-fields-assets/.publish.lock', 0600), 'Cannot create isolated FIFO fixture.');
        releaseReject(fn () => PublicationLock::run($badRoot, static fn (): bool => true), 'FIFO lock file accepted and could block before the timeout.');
        unlink($badRoot.'/filament-flex-fields-assets/.publish.lock');
    }
    releaseReject(fn () => PublicationLock::run($public, static fn (): bool => true, 0), 'Unbounded publication lock timeout accepted.');
    $proofs[] = 'Invalid retention policies, executable public artifacts, special lock files and release destinations reject before artifact mutations.';

    $race = releaseFixture($work.'/race', 'race');
    $racePublic = $work.'/fresh-public';
    $filesystem->ensureDirectoryExists($racePublic);
    $writers = [];
    for ($index = 0; $index < 2; $index++) {
        $child = proc_open([PHP_BINARY, __FILE__, '--publish-release', $work.'/race', $racePublic], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $work.'/race.log', 'a']], $pipes);
        releaseCheck(is_resource($child), 'Cannot start concurrent writer.');
        fclose($pipes[0]);
        $writers[] = [$child, $pipes[1]];
    }
    $copied = 0;
    $unchanged = 0;
    foreach ($writers as [$child, $output]) {
        $json = stream_get_contents($output);
        fclose($output);
        releaseCheck(proc_close($child) === 0, 'Concurrent writer failed: '.file_get_contents($work.'/race.log'));
        $row = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $copied += $row['copied'];
        $unchanged += $row['unchanged'];
    }
    releaseCheck($copied === 4 && $unchanged === 4, 'Concurrent writers did not commit exactly once.');
    $publisher->verify($race->inventory(), $racePublic.'/'.$race->publicDirectory($prefix));
    $proofs[] = 'Two independent PHP processes in a fresh public root safely create lock metadata and publish the same missing release exactly once under the root lock; the follower verifies the complete existing directory.';

    $packages = new PackageReleasePublisher($filesystem);
    $packageRetention = new PackageReleaseRetention($filesystem);
    $history = [];
    foreach (['A', 'B', 'C', 'D'] as $version) {
        $history[$version] = portableReleaseFixture($work.'/portable-'.$version, $version);
        PublicationLock::run($public, fn (string $root): array => $packages->publish($history[$version], $root, $prefix));
        touch($public.'/'.$history[$version]->publicDirectory($prefix).'/.release.json', time() - 40 * 86400 + ord($version));
    }
    $other = portableReleaseFixture($work.'/other-family', 'B', 'other/author');
    PublicationLock::run($public, fn (string $root): array => $packages->publish($other, $root, $prefix));
    $protected = [$history['C']->manifest->release];
    $rows = $packageRetention->run($public, $prefix, $history['A'], 1, 30 * 86400, $protected);
    releaseCheck(array_column(array_filter($rows, fn (array $row): bool => $row['eligible']), 'release') === [$history['B']->manifest->release], 'Package retention did not protect current, newest and explicit other-node releases.');
    $corrupt = $public.'/'.$history['C']->publicDirectory($prefix).'/entry.js';
    $original = file_get_contents($corrupt);
    file_put_contents($corrupt, 'changed');
    releaseReject(fn () => $packageRetention->run($public, $prefix, $history['A'], 1, 30 * 86400, $protected, true), 'Package history corruption was accepted.');
    releaseCheck(is_dir($public.'/'.$history['B']->publicDirectory($prefix)), 'History verification deleted a valid eligible package before finding corruption.');
    file_put_contents($corrupt, $original);
    $marker = $public.'/'.$history['C']->publicDirectory($prefix).'/.release.json';
    $originalMarker = file_get_contents($marker);
    file_put_contents($marker, $other->manifest->toJson());
    releaseReject(fn () => $packageRetention->run($public, $prefix, $history['A']), 'Foreign package marker accepted in another package directory.');
    file_put_contents($marker, $originalMarker);
    touch($marker, time() - 40 * 86400 + ord('C'));
    $filesystem->deleteDirectory($history['B']->sourceRoot);
    $packageRetention->run($public, $prefix, $history['A'], 1, 30 * 86400, $protected, true);
    releaseCheck(! is_dir($public.'/'.$history['B']->publicDirectory($prefix)) && is_dir($public.'/'.$other->publicDirectory($prefix)) && is_dir($public.'/'.$a->publicDirectory($prefix)), 'Package pruning needs old sources or removed another package/core.');
    $history['B'] = portableReleaseFixture($work.'/portable-B', 'B');
    PublicationLock::run($public, fn (string $root): array => $packages->publish($history['B'], $root, $prefix));
    $packages->verifyPublished($history['B'], $public, $prefix);
    $proofs[] = 'Portable histories use the same verified retention algorithm; current/newest/other-node protection, all-history preflight, foreign marker rejection, absent old source checkout and package/core isolation survive prune and restoration.';

    $savedRegistry = app(ComponentRegistry::class);
    $packageRegistry = new ComponentRegistry;
    $packageRegistry->registerBatch([new ComponentDefinition('author-control', ComponentKind::Field, styles: ['author-style'], entry: 'author-entry')], ['author-control' => $history['A']]);
    app()->instance(ComponentRegistry::class, $packageRegistry);
    try {
        config()->set('filament.assets_path', $prefix);
        releaseCheck(Artisan::call('fff:assets:releases', ['--package' => 'acme/author', '--public-root' => $public, '--json' => true]) === 0, 'Installed package retention CLI failed.');
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        releaseCheck($report['package'] === 'acme/author' && $report['current'] === $history['A']->manifest->release && count($report['releases']) === 4, 'CLI package selection differs from the immutable registry snapshot.');
        releaseCheck(Artisan::call('fff:assets:releases', ['--package' => '../escape', '--prune' => true, '--public-root' => $public, '--json' => true]) === 1, 'Unregistered package selection was accepted.');
        releaseCheck(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR) === ['error' => 'release_retention_failed'], 'Package diagnostics leaked paths or installed metadata.');
        releaseCheck(is_dir($public.'/'.$history['A']->publicDirectory($prefix)), 'Unknown package command modified release history.');
    } finally {
        config()->set('filament.assets_path', '');
        app()->instance(ComponentRegistry::class, $savedRegistry);
    }
    $proofs[] = 'Actual CLI selects only one registered Composer package, derives the protected current identity from the registry, previews independently and fails unknown selection with redacted diagnostics.';

    if (function_exists('posix_mkfifo')) {
        $target = $public.'/'.$history['C']->publicDirectory($prefix).'/.release.json';
        $content = file_get_contents($target);
        unlink($target);
        releaseCheck(posix_mkfifo($target, 0600), 'Cannot create portable marker FIFO fixture.');
        releaseReject(fn () => $packageRetention->run($public, $prefix, $history['A']), 'Special portable marker accepted.');
        releaseReject(fn () => $packages->verifyPublished($history['C'], $public, $prefix), 'Public directory verifier accepted a special marker file.');
        unlink($target);
        file_put_contents($target, $content);
        $target = $public.'/'.$a->publicDirectory($prefix).'/.release.json';
        $content = file_get_contents($target);
        unlink($target);
        releaseCheck(posix_mkfifo($target, 0600), 'Cannot create core marker FIFO fixture.');
        releaseReject(fn () => $retention->run($public, $prefix, $hash($a)), 'Special builtin marker accepted.');
        unlink($target);
        file_put_contents($target, $content);
    }
    $missing = portableReleaseFixture($work.'/missing-current', 'missing');
    releaseReject(fn () => $packageRetention->run($public, $prefix, $missing, 1, 86400, [], true), 'Prune allowed before installed package release publication.');
    $empty = $work.'/empty-history';
    $filesystem->ensureDirectoryExists($empty);
    releaseCheck($packageRetention->run($empty, $prefix, $missing) === [] && $retention->run($empty, $prefix, $hash($a)) === [], 'Empty history preview failed.');
    releaseReject(fn () => $packageRetention->run($empty, $prefix, $missing, prune: true), 'Missing entire package history allowed prune without current release.');
    releaseReject(fn () => $retention->run($empty, $prefix, $hash($a), prune: true), 'Missing entire core history allowed prune without current release.');
    $proofs[] = 'Both marker readers and shared public verifier refuse FIFO/special files before reads; package pruning rejects an unpublished current release.';

    $recoveryRoot = $work.'/recovery-budget';
    $recoveryParent = $recoveryRoot.'/'.dirname($a->publicDirectory($prefix));
    $filesystem->ensureDirectoryExists($recoveryParent);
    for ($index = 0; $index < 257; $index++) {
        mkdir($recoveryParent.'/.fff-stage-'.sprintf('%024x', $index));
    }
    releaseReject(fn () => PublicationLock::run($recoveryRoot, fn (string $root): array => $publisher->publish($a->inventory(), $root, $a->publicDirectory($prefix))), 'Excessive recovery candidates accepted.');
    releaseCheck(count(glob($recoveryParent.'/.fff-stage-*')) === 257, 'Recovery deleted candidates before completing bounded admission.');
    $filesystem->deleteDirectory($recoveryParent);
    $filesystem->ensureDirectoryExists($recoveryParent);
    for ($index = 0; $index < 8193; $index++) {
        file_put_contents($recoveryParent.'/unknown-'.$index, '');
    }
    releaseReject(fn () => PublicationLock::run($recoveryRoot, fn (string $root): array => $publisher->publish($a->inventory(), $root, $a->publicDirectory($prefix))), 'Unbounded release parent scan accepted.');
    releaseCheck(! is_dir($recoveryRoot.'/'.$a->publicDirectory($prefix)) && is_file($recoveryParent.'/unknown-8192'), 'Oversized recovery mutated unrelated entries or exposed a release.');
    $proofs[] = 'Stage recovery scans at most 8192 parent entries and retains at most 256 candidate paths; excessive state fails before deletion/copy without collecting an unbounded glob result.';

    if (in_array('--browser-fixtures', $argv, true)) {
        $preserve = true;
        echo 'BROWSER_FIXTURES:'.base64_encode(json_encode(['work' => $work, 'public' => $public, 'a' => $a->publicDirectory($prefix), 'b' => $b->publicDirectory($prefix), 'packageA' => $history['A']->publicDirectory($prefix), 'packageB' => $history['B']->publicDirectory($prefix)], JSON_THROW_ON_ERROR)).PHP_EOL;
    }
    echo json_encode(['groups' => count($proofs), 'proofs' => $proofs, 'actualArtifacts' => count($native->inventory()->files)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    if (! $preserve) {
        $filesystem->deleteDirectory($work);
    }
}
