<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\CountryRegistryQueue;
use Bjanczak\FilamentFlexFields\Support\CurrencyRegistryQueue;
use Bjanczak\FilamentFlexFields\Support\HtmlSanitizer;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogResolver;
use Bjanczak\FilamentFlexFields\Support\Icons\IconSvgCache;
use Bjanczak\FilamentFlexFields\Support\Runtime\OptionalSharedCache;
use Bjanczak\FilamentFlexFields\Support\Runtime\ScopedRenderCache;
use Bjanczak\FilamentFlexFields\Support\Select\Catalog\PickerCatalogState;
use Bjanczak\FilamentFlexFields\Support\TimezoneRegistryQueue;
use Bjanczak\FilamentFlexFields\Support\UserSelectQueryCache;
use Illuminate\Contracts\Foundation\Application;

final class FieldServices
{
    public static function register(Application $app): void
    {
        $app->singleton(HtmlSanitizer::class);
        $app->scoped(CountryRegistryQueue::class);
        $app->scoped(TimezoneRegistryQueue::class);
        $app->scoped(CurrencyRegistryQueue::class);
        $app->scoped(UserSelectQueryCache::class);
        $app->scoped(PickerCatalogState::class);
        $app->scoped(ScopedRenderCache::class);
        $app->scoped(OptionalSharedCache::class);
        $app->singleton(IconCatalogResolver::class);
        $app->singleton(IconSvgCache::class);
    }
}
