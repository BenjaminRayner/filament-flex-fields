<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Core;

use Bjanczak\FilamentFlexFields\Support\Events\FieldMounted;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingOs;
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

final class WorkerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(WorkerReport::class);
    }

    public function boot(): void
    {
        $this->app->make(Dispatcher::class)->listen(FieldMounted::class, WorkerActivityListener::class);
        // Resolve the current container inside the factory, never capture a tenant or root app.
        $factory = static function (): TenantProvider {
            app(WorkerReport::class)->factoryCalls++;

            return new TenantProvider(app(TenantContext::class)->id);
        };
        GeocodingOs::registerProviderFactory($factory);
        FlexMedia::registerTranscriptionFactory($factory);
    }
}
