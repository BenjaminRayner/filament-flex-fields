<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use InvalidArgumentException;

/** Resolve declared component resources without per-request content hashing. */
final class PublishedResourceUrl
{
    public static function for(string $component, string $resource, string $file): string
    {
        if (! preg_match('~^[a-zA-Z0-9_./-]+$~D', $file)
            || str_starts_with($file, '/')
            || array_intersect(['', '.', '..'], explode('/', $file)) !== []) {
            throw new InvalidArgumentException('Invalid published resource filename.');
        }

        foreach (ComponentAssetCatalog::registry()->definition($component)->publications as $publication) {
            if ($publication->id !== $resource) {
                continue;
            }

            $prefix = config('filament.assets_path') ?? '';
            if (! is_string($prefix)) {
                throw new InvalidArgumentException('Filament asset prefix must be a string.');
            }
            $immutable = app(BuiltInAssetRelease::class)->publicPath($publication->sourcePath().'/'.$file, $prefix);
            if ($immutable !== null) {
                return asset($immutable);
            }

            $url = asset(rtrim($publication->publicDirectory, '/').'/'.$file);
            $version = app(AssetVersionManifest::class)->versionFor($publication->sourcePath().'/'.$file);

            return $version === null ? $url : $url.'?v='.$version;
        }

        throw new InvalidArgumentException("Component [{$component}] does not declare resource [{$resource}].");
    }
}
