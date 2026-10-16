<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Filament\View\PanelsRenderHook;

/** Explicit layout positions; bootstrap must precede native Alpine startup. */
enum RuntimeRenderPhase: string
{
    case HeadStart = 'head-start';
    case HeadEnd = 'head-end';
    case StylesAfter = 'styles-after';
    case BodyEnd = 'body-end';
    case ScriptsBefore = 'scripts-before';
    case ScriptsAfter = 'scripts-after';

    public static function fromHook(string $hook): ?self
    {
        foreach (self::cases() as $phase) {
            if ($phase->hook() === $hook) {
                return $phase;
            }
        }

        return null;
    }

    public function hook(): string
    {
        return match ($this) {
            self::HeadStart => PanelsRenderHook::HEAD_START,
            self::HeadEnd => PanelsRenderHook::HEAD_END,
            self::StylesAfter => PanelsRenderHook::STYLES_AFTER,
            self::BodyEnd => PanelsRenderHook::BODY_END,
            self::ScriptsBefore => PanelsRenderHook::SCRIPTS_BEFORE,
            self::ScriptsAfter => PanelsRenderHook::SCRIPTS_AFTER,
        };
    }
}
