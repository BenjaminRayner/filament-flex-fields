<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Panel-local settings; authorization is evaluated for the current request. */
final class PlaygroundAccess
{
    public static function isEnabled(?Panel $panel = null): bool
    {
        if (! config('filament-flex-fields.enabled', true) || ! FlexFieldsConfig::isPlaygroundEnabled()) {
            return false;
        }
        $panel ??= Filament::getCurrentPanel();

        $plugin = $panel?->hasPlugin('filament-flex-fields') ? $panel->getPlugin('filament-flex-fields') : null;

        return ! $plugin instanceof FilamentFlexFieldsPlugin || $plugin->isPlaygroundEnabled();
    }

    public static function canAccess(): bool
    {
        $panel = Filament::getCurrentPanel();
        if (! self::isEnabled($panel)) {
            return false;
        }
        $user = $panel ? Filament::auth()->user() : Auth::user();
        if ($user === null) {
            return false;
        }
        $ability = config('filament-flex-fields.playground.gate');
        if ($ability !== null && (! is_string($ability) || ! Gate::forUser($user)->allows($ability))) {
            return false;
        }
        $plugin = $panel?->hasPlugin('filament-flex-fields') ? $panel->getPlugin('filament-flex-fields') : null;
        if ($plugin instanceof FilamentFlexFieldsPlugin && $plugin->getPlaygroundAuthorization() !== null) {
            return (bool) app()->call($plugin->getPlaygroundAuthorization(), ['user' => $user, 'panel' => $panel]);
        }

        return true;
    }
}
