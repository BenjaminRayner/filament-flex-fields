<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Build;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/** Never used by the build validator or publisher. Disabled by default. */
final class DevelopmentAssetFallback
{
    public static function permitted(): bool
    {
        $app = Container::getInstance();

        return $app instanceof Application && $app->environment('local', 'testing')
            && config('filament-flex-fields.assets.development_fallback', false) === true;
    }

    public static function report(RuntimeException $exception): void
    {
        if (! self::permitted()) {
            throw $exception;
        }
        logger()->warning('FLEX development asset fallback: '.$exception->getMessage());
    }
}
