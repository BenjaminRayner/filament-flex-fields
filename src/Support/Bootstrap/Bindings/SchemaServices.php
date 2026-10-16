<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Bjanczak\FilamentFlexFields\Support\FlexFieldSchemaRegistry;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\FieldComponentFactory;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Registry\FieldTypeHandlerRegistry;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldEntityDiscovery;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldEntityRegistry;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldFilterBuilder;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldGroupRegistrySync;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldGroupValidator;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldInfolistBuilder;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldSchemaResolver;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldStudio;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldTableBuilder;
use Illuminate\Contracts\Foundation\Application;

final class SchemaServices
{
    public static function register(Application $app): void
    {
        $app->scoped(FlexFieldSchemaRegistry::class, function (): FlexFieldSchemaRegistry {
            $registry = new FlexFieldSchemaRegistry;

            $registry->registerFromConfig(config('filament-flex-fields.schemas', []));

            $registry->loadUsing(
                function (string $target): void {
                    app(FlexFieldGroupRegistrySync::class)->syncForTarget($target);
                },
                function (string $key): void {
                    app(FlexFieldGroupRegistrySync::class)->syncById($key);
                },
            );

            return $registry;
        });

        $app->scoped(FlexFieldGroupRegistrySync::class);
        $app->singleton(FlexFieldEntityDiscovery::class);
        $app->scoped(FlexFieldEntityRegistry::class);
        $app->scoped(FlexFieldSchemaResolver::class);
        $app->scoped(FlexFieldFilterBuilder::class);
        $app->singleton(FlexFieldGroupValidator::class);
        $app->scoped(FlexFieldTableBuilder::class);
        $app->scoped(FlexFieldInfolistBuilder::class);
        $app->scoped(FlexFieldStudio::class);

        $app->singleton(FieldTypeHandlerRegistry::class);
        $app->scoped(FieldComponentFactory::class);
        $app->scoped(FlexFieldFormBuilder::class);
    }
}
