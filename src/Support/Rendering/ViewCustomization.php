<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;

/** Native listener lookup already invalidates its wildcard cache on registration. */
final class ViewCustomization
{
    public static function hasHooks(string $view, DispatcherContract $events): bool
    {
        if ($events::class === Dispatcher::class) {
            return $events->getListeners('composing: '.$view) !== []
                || $events->getListeners('creating: '.$view) !== [];
        }

        return $events->hasListeners('composing: '.$view) || $events->hasListeners('creating: '.$view);
    }
}
