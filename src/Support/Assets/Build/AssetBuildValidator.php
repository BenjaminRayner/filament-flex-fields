<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Build;

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\BuiltInComponentIndex;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactPath;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifestCompiler;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\AssetRegistration;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Throwable;

/** Deployment-only, read-only validation; never consults the development fallback. */
final class AssetBuildValidator
{
    public function validate(?ComponentRegistry $registry = null, ?string $packageRoot = null): BuildValidationReport
    {
        $root = $packageRoot ?? dirname(__DIR__, 4);
        $seal = new AssetVersionManifest($root, allowDevelopmentFallback: false);
        $release = null;
        $artifacts = 0;
        $packages = 0;
        $errors = [];
        $errorCount = 0;
        $error = static function (string $code, string $subject, Throwable $exception) use (&$errors, &$errorCount): void {
            $errorCount++;
            if (count($errors) < 128) {
                $errors[] = ['code' => $code, 'subject' => substr($subject, 0, 512), 'message' => substr($exception->getMessage(), 0, 2048)];
            }
        };
        $verify = static function (string $sourceRoot, string $file, string $digest, ?int $bytes = null): void {
            $source = ArtifactPath::source(ArtifactPath::root($sourceRoot), $file);
            clearstatcache(true, $source);
            if (($bytes !== null && filesize($source) !== $bytes) || hash_file('sha256', $source) !== $digest) {
                throw new \RuntimeException('File differs from its sealed hash or byte size. Rebuild before deployment.');
            }
        };
        $assets = [];
        try {
            $assets = $seal->assets();
            $release = $seal->release();
            (new BuiltInAssetRelease($seal))->inventory();
            foreach ($seal->metadata() as $file => $digest) {
                try {
                    $verify($root, $file, $digest);
                } catch (Throwable $exception) {
                    $error('metadata-parity', $file, $exception);
                }
            }
            foreach ($assets as $file => $digest) {
                $artifacts++;
                try {
                    $verify($root.'/resources/dist', $file, $digest, $seal->bytesFor($file));
                } catch (Throwable $exception) {
                    $error('artifact-parity', $file, $exception);
                }
            }
        } catch (Throwable $exception) {
            $error('release-seal', 'resources/dist/asset-versions.json', $exception);
        }
        try {
            $alpine = new AlpineBuildManifest($root.'/resources/dist/components/alpine-manifest.json');
            $manifest = $alpine->data();
            $entries = $manifest['__entries__'];
            $chunks = $manifest['__shared_chunks__'];
            foreach ([...array_map(static fn (string $entry): string => 'components/'.$entry.'.js', $entries),
                ...array_map(static fn (string $chunk): string => 'components/'.$chunk, $chunks),
                'css/core.css', ...array_map(static fn (string $style): string => 'css/'.$style.'.css', BuiltInComponentIndex::LAZY_COMPONENT_STYLESHEETS),
                ...array_values(AssetRegistration::SCRIPT_FILES)] as $file) {
                if (! isset($assets[$file])) {
                    $error('missing-root', $file, new \RuntimeException('Declared build root is absent from the release seal.'));
                }
            }

            foreach (['segment', 'timezone', 'readiness'] as $name) {
                $file = 'components/'.$alpine->firstPaintFile($name);
                if (! isset($assets[$file]) || ! str_ends_with($file, '-'.$assets[$file].'.js')) {
                    $error('first-paint-owner', $file, new \RuntimeException('First-paint filename must match its sealed content hash.'));
                }
            }

            foreach (ComponentRegistry::builtIn()->all() as $definition) {
                if ($definition->entry !== null) {
                    if (! in_array($definition->entry, $entries, true)) {
                        $error('missing-entry', $definition->id, new \RuntimeException('Typed component entry is absent from the Alpine graph: '.$definition->entry));
                    }
                }
            }
            foreach (BuiltInProjection::compile() as $name => $expected) {
                if (constant(BuiltInComponentIndex::class.'::'.$name) !== $expected) {
                    $error('php-projection', $name, new \RuntimeException('Generated PHP projection differs from typed component definitions.'));
                }
            }
        } catch (Throwable $exception) {
            $error('alpine-graph', 'resources/dist/components/alpine-manifest.json', $exception);
        }
        if ($registry !== null) {
            foreach ($registry->packageAssets()->packages as $package) {
                $packages++;
                try {
                    (new AssetManifestCompiler)->verifySources($package->manifest, $package->sourceRoot);
                } catch (Throwable $exception) {
                    $error('package-parity', $package->manifest->package, $exception);
                }
            }
        }

        return new BuildValidationReport($release, $artifacts, $packages, $errors, $errorCount);
    }
}
