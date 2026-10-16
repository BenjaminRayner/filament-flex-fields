<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap;

use BladeUI\Icons\Factory;
use Illuminate\Contracts\Foundation\Application;

/** Icon sets are registered without resolving the icon factory at boot. */
final class IconRegistration
{
    public static function register(Application $app): void
    {
        $register = static function (Factory $factory): void {
            $factory->add('flex-brand', [
                'path' => dirname(__DIR__, 3).'/resources/svg/brand',
                'prefix' => 'flexbrand',
            ]);
            $factory->add('flex-hugeicons', [
                'path' => dirname(__DIR__, 3).'/resources/svg/hugeicons',
                'prefix' => 'flexhugeicons',
            ]);
            $factory->add('flex-videojs', [
                'path' => dirname(__DIR__, 3).'/resources/svg/videojs',
                'prefix' => 'flexvideojs',
            ]);
        };

        $app->afterResolving(Factory::class, $register);
        if ($app->resolved(Factory::class)) {
            $register($app->make(Factory::class));
        }

    }
}
