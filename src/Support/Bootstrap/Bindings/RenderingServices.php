<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\AssetPlanDictionary;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\LivewireRenderState;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\ResolvedAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext;
use Bjanczak\FilamentFlexFields\Support\Rendering\BladePresentation;
use Bjanczak\FilamentFlexFields\Support\Rendering\CatalogPayloadTransport;
use Bjanczak\FilamentFlexFields\Support\Rendering\ControlConfigurationDictionary;
use Bjanczak\FilamentFlexFields\Support\Rendering\NativeFieldWrapper;
use Bjanczak\FilamentFlexFields\Support\Rendering\PortalTemplateDictionary;
use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlIcon;
use Bjanczak\FilamentFlexFields\Support\Rendering\StockFieldRenderer;
use Bjanczak\FilamentFlexFields\Support\Select\Bootstrap\RenderedSelectMenu;
use Illuminate\Contracts\Foundation\Application;

final class RenderingServices
{
    public static function register(Application $app): void
    {
        // Document intent, render plans and emission state never cross request scopes.
        $app->scoped(AssetPlanDictionary::class);
        $app->scoped(ResolvedAssetPlan::class);
        $app->scoped(RenderedSelectMenu::class);
        $app->scoped(NativeFieldWrapper::class);
        $app->scoped(BladePresentation::class);
        $app->scoped(ControlConfigurationDictionary::class);
        $app->scoped(CatalogPayloadTransport::class);
        $app->scoped(StockFieldRenderer::class);
        $app->scoped(RenderedControlIcon::class);
        $app->scoped(PortalTemplateDictionary::class);
        $app->scoped(LivewireRenderState::class);
        $app->scoped(RuntimeRenderContext::class);
    }
}
