<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap;

use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\AppearanceServices;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\AssetServices;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\EnterpriseServices;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\FieldServices;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\IntegrationServices;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\MediaServices;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\PlaygroundServices;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\RenderingServices;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings\SchemaServices;
use Illuminate\Contracts\Foundation\Application;

/** Lazy bindings grouped by ownership; request state never becomes a process singleton. */
final class ServiceBindings
{
    public static function register(Application $app): void
    {
        AssetServices::register($app);
        RenderingServices::register($app);
        SchemaServices::register($app);
        FieldServices::register($app);
        MediaServices::register($app);
        AppearanceServices::register($app);
        PlaygroundServices::register($app);
        EnterpriseServices::register($app);
        IntegrationServices::register($app);
    }
}
