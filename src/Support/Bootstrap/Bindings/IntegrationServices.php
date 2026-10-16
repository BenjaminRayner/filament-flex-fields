<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingState;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureState;
use Illuminate\Contracts\Foundation\Application;

final class IntegrationServices
{
    public static function register(Application $app): void
    {
        $app->scoped(GeocodingState::class);
        $app->scoped(MediaCaptureState::class);
        $app->scoped(ActivityDispatcher::class);
    }
}
