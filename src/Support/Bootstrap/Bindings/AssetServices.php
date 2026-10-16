<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\AlpineBuildManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\FirstPaintScripts;
use Bjanczak\FilamentFlexFields\Support\Catalog\CategoryRegistry;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\ComponentRegistrationCollector;
use Bjanczak\FilamentFlexFields\Support\Components\Routing\ComponentRouteRegistrar;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAlpineQueue;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Illuminate\Contracts\Foundation\Application;

final class AssetServices
{
    public static function register(Application $app): void
    {
        $app->singleton(ComponentRegistry::class,
            static fn () => ComponentRegistry::builtIn());
        $app->singleton(ComponentRegistrationCollector::class);
        $app->singleton(ComponentRegistrar::class);
        $app->singleton(ComponentRouteRegistrar::class);
        $app->booted(static function () use ($app): void {
            $app->make(ComponentRegistrar::class)->completeCollection();
            $app->make(ComponentRegistry::class)->freeze();
            $app->make(ComponentRouteRegistrar::class)->register($app, $app->make(ComponentRegistry::class));
            $app->afterResolving(FlexCatalog::class, static function (FlexCatalog $catalog): void {
                $catalog->freeze();
            });
            if ($app->resolved(FlexCatalog::class)) {
                $app->make(FlexCatalog::class)->freeze();
            }
        });
        $app->singleton(CategoryRegistry::class);
        $app->singleton(FlexCatalog::class);
        $app->scoped(FirstPaintScripts::class);
        $app->scoped(AssetDeliveryContext::class);
        $app->scoped(AssetVersionManifest::class);
        $app->scoped(BuiltInAssetRelease::class);
        $app->scoped(AlpineBuildManifest::class);
        $app->scoped(FlexFieldStylesheetQueue::class);
        $app->scoped(FlexFieldAlpineQueue::class);
    }
}
