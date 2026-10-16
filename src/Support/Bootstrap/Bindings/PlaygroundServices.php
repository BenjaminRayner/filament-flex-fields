<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\Playground\PlaygroundRegistryState;
use Illuminate\Contracts\Foundation\Application;

final class PlaygroundServices
{
    public static function register(Application $app): void
    {
        $app->scoped(PlaygroundRegistryState::class);
    }
}
