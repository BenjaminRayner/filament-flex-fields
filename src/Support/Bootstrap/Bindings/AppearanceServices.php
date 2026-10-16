<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\Theme\FlexFieldsTheme;
use Illuminate\Contracts\Foundation\Application;

final class AppearanceServices
{
    public static function register(Application $app): void
    {
        $app->scoped(FlexFieldsTheme::class, function (): FlexFieldsTheme {
            $theme = new FlexFieldsTheme;

            $theme
                ->setDensity(FlexFieldsConfig::getDensity())
                ->mergeTheme(FlexFieldsConfig::getTheme());

            return $theme;
        });
    }
}
