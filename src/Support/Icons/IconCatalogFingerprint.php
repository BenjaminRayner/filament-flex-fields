<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Icons;

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Composer\InstalledVersions;

/** Cache identity includes catalog contents and the deployed source revision. */
final class IconCatalogFingerprint
{
    /** @param array<array-key, mixed> $data */
    public static function of(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function namespace(): string
    {
        $root = InstalledVersions::getRootPackage();

        return self::of([
            'json-cache-v1',
            (string) config('filament-flex-fields.ui.icon_picker_cache_namespace', 'v4'),
            RequestState::resolve(AssetVersionManifest::class)->release(),
            $root['name'], $root['reference'] ?? null,
            InstalledVersions::isInstalled('janczakb/blade-hugeicons')
                ? InstalledVersions::getReference('janczakb/blade-hugeicons') : null,
            function_exists('app') && method_exists(app(), 'getLocale') ? app()->getLocale() : 'en',
        ]);
    }
}
