<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Build;

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PackageReleasePublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PublicationLock;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\ReleaseDirectoryPublisher;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Illuminate\Filesystem\Filesystem;
use Throwable;

/** Explicit deployment readback, serialized with publishers and retention. */
final class PublishedBuildValidator
{
    public function __construct(private readonly Filesystem $filesystem) {}

    public function validate(BuildValidationReport $source, ComponentRegistry $registry, string $publicRoot, string $prefix): BuildValidationReport
    {
        $errors = $source->errors;
        $errorCount = $source->errorCount;
        $fail = static function (string $subject) use (&$errors, &$errorCount): void {
            $errorCount++;
            if (count($errors) < 128) {
                $errors[] = ['code' => 'published_release_invalid', 'subject' => $subject,
                    'message' => 'Missing, unsafe, incomplete or modified published release; restore a verified complete copy before activation.'];
            }
        };
        try {
            PublicationLock::run($publicRoot, function (string $root) use ($registry, $prefix, $fail): void {
                $builtin = new BuiltInAssetRelease(new AssetVersionManifest(allowDevelopmentFallback: false));
                $publisher = new ReleaseDirectoryPublisher($this->filesystem);
                try {
                    $directory = $publisher->destination($root, $builtin->publicDirectory($prefix));
                    $publisher->verify($builtin->inventory(), $directory);
                } catch (Throwable) {
                    $fail('core');
                }
                $packagePublisher = new PackageReleasePublisher($this->filesystem);
                foreach ($registry->packageAssets()->packages as $package) {
                    try {
                        $packagePublisher->verifyPublished($package, $root, $prefix);
                    } catch (Throwable) {
                        $fail($package->manifest->package);
                    }
                }
            });
        } catch (Throwable) {
            $fail('public-root');
        }

        return new BuildValidationReport($source->release, $source->artifacts, $source->packages, $errors, $errorCount);
    }
}
