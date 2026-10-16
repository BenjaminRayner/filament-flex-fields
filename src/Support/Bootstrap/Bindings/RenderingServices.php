<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\AssetPlanDictionary;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\LivewireRenderState;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext;
use Illuminate\Contracts\Foundation\Application;

final class RenderingServices
{
    public static function register(Application $app): void
    {
        // Document intent, render plans and emission state never cross request scopes.
        $app->scoped(AssetPlanDictionary::class);
        $app->scoped(LivewireRenderState::class);
        $app->scoped(RuntimeRenderContext::class);
    }
}
