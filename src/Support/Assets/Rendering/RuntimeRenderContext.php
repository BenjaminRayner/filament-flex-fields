<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Filament\Facades\Filament;

/** Request-local rendering intent, independent of component asset ownership. */
final class RuntimeRenderContext
{
    private bool $standalone = false;

    /** @var array<string, true> */
    private array $emitted = [];

    /** @var array<string, true> */
    private array $phases = [];

    public function enableStandalone(): void
    {
        $this->standalone = true;
    }

    public function allows(): bool
    {
        if (! config('filament-flex-fields.enabled', true)) {
            return false;
        }

        $panel = app()->bound('filament') ? Filament::getCurrentPanel() : null;

        return $panel !== null ? $panel->hasPlugin('filament-flex-fields') : $this->standalone;
    }

    public function claim(RuntimeEmission $emission): bool
    {
        if (! $this->allows() || request()->hasHeader('X-Livewire') || isset($this->emitted[$emission->value])) {
            return false;
        }
        $this->emitted[$emission->value] = true;

        return true;
    }

    public function claimPhase(RuntimeRenderPhase $phase): bool
    {
        if (! $this->allows() || request()->hasHeader('X-Livewire') || isset($this->phases[$phase->value])) {
            return false;
        }
        $this->phases[$phase->value] = true;

        return true;
    }
}
