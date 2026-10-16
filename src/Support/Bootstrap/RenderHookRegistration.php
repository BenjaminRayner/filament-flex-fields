<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap;

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderPhase;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

/** One ordered callback per render phase; early and late queues remain distinct. */
final class RenderHookRegistration
{
    public static function register(): void
    {
        foreach (self::phases() as $phase => $parts) {
            FilamentView::registerRenderHook($phase, static fn (): string => self::render($parts, RuntimeRenderPhase::fromHook($phase)));
        }
    }

    /** Render only FLEX's layout fragments, without invoking another plugin's hooks. */
    public static function renderStandalone(RuntimeRenderPhase $phase): string
    {
        app(RuntimeRenderContext::class)->enableStandalone();

        return self::render(self::phases()[$phase->hook()], $phase);
    }

    /** @return array<string, list<array{view: string, catalog: bool}>> */
    private static function phases(): array
    {
        $playground = FlexFieldsConfig::isPlaygroundEnabled();
        $phases = [
            PanelsRenderHook::HEAD_START => [
                ['view' => 'asset-delivery-policy', 'catalog' => false],
                ['view' => 'flex-fields-theme', 'catalog' => false],
            ],
            PanelsRenderHook::HEAD_END => [
                ...($playground ? [['view' => 'playground-theme', 'catalog' => true]] : []),
                ['view' => 'critical-stylesheets', 'catalog' => false],
            ],
            PanelsRenderHook::STYLES_AFTER => [
                ['view' => 'queued-stylesheets', 'catalog' => false],
                ['view' => 'tooltip-overrides', 'catalog' => false],
            ],
            PanelsRenderHook::BODY_END => [
                ['view' => 'queued-stylesheets', 'catalog' => false],
            ],
            PanelsRenderHook::SCRIPTS_BEFORE => [
                ['view' => 'flex-fff-load-bootstrap', 'catalog' => false],
            ],
            PanelsRenderHook::SCRIPTS_AFTER => [
                ['view' => 'tooltip-glass-script', 'catalog' => false],
                ['view' => 'flex-field-asset-injector', 'catalog' => false],
            ],
        ];
        if ($playground) {
            $phases[PanelsRenderHook::STYLES_BEFORE] = [
                ['view' => 'playground-page-stylesheets', 'catalog' => true],
            ];
        }

        return $phases;
    }

    /** @param list<array{view: string, catalog: bool}> $parts */
    private static function render(array $parts, ?RuntimeRenderPhase $phase = null): string
    {
        $context = app(RuntimeRenderContext::class);
        if (! $context->allows() || ($phase !== null && ! $context->claimPhase($phase))) {
            return '';
        }
        $html = '';
        foreach ($parts as $part) {
            if ($part['catalog'] && ! FlexFieldAssets::isPlaygroundRequest()) {
                continue;
            }
            $html .= view('filament-flex-fields::partials.'.$part['view'])->render();
        }

        return $html;
    }
}
