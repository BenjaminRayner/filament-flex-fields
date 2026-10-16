<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactPath;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifestCompiler;

// Build tooling only. No host application, request or component values are booted.
if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(STDERR, "Usage: php scripts/assets/compile-package-manifest.php <build-spec.json> <artifact-root> <output.json>\n");
    exit(1);
}
$temporary = null;
$exitCode = 0;
try {
    $packageRoot = dirname(__DIR__, 2);
    $explicitAutoload = getenv('FLEX_FIELDS_AUTOLOAD');
    $autoloadCandidates = $explicitAutoload === false ? [
        $packageRoot.'/vendor/autoload.php',
        dirname($packageRoot, 2).'/autoload.php',
        getcwd().'/vendor/autoload.php',
    ] : [$explicitAutoload];
    $loaded = false;
    foreach ($autoloadCandidates as $candidate) {
        if ($candidate !== '' && strlen($candidate) <= 4096 && ! str_contains($candidate, "\0") && is_file($candidate) && is_readable($candidate)) {
            require_once $candidate;
            $loaded = true;
            break;
        }
    }
    if (! $loaded || ! class_exists(AssetManifestCompiler::class)) {
        throw new RuntimeException('FLEX build autoload is unavailable. Install Composer dependencies or set FLEX_FIELDS_AUTOLOAD to their vendor/autoload.php.');
    }
    $compiler = new AssetManifestCompiler;
    $root = ArtifactPath::root($argv[2]);
    $manifest = $compiler->build($argv[1], $root);
    $compiler->verifySources($manifest, $root);
    $directory = realpath(dirname($argv[3]));
    if ($directory === false || ! is_dir($directory) || is_link($argv[3])) {
        throw new RuntimeException('Manifest output directory must exist; symlink outputs are not allowed.');
    }
    $output = $directory.DIRECTORY_SEPARATOR.basename($argv[3]);
    if ($output === realpath($argv[1])) {
        throw new RuntimeException('Manifest output cannot replace its build specification.');
    }
    foreach ($manifest->artifacts as $artifact) {
        if ($output === ArtifactPath::source($root, $artifact->path)) {
            throw new RuntimeException('Manifest output cannot replace a build artifact.');
        }
    }
    $temporary = tempnam($directory, '.fff-manifest-');
    $json = $manifest->toJson();
    if ($temporary === false || file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)
        || ! chmod($temporary, 0644) || ! rename($temporary, $output)) {
        throw new RuntimeException('Cannot write the compiled manifest.');
    }
    fwrite(STDOUT, json_encode(['package' => $manifest->package, 'release' => $manifest->release,
        'artifacts' => count($manifest->artifacts)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    if (is_string($temporary) && is_file($temporary)) {
        unlink($temporary);
    }
}
exit($exitCode);
