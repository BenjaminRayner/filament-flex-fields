<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Filament\Support\SupportServiceProvider;
use Livewire\LivewireServiceProvider;

/** Boot native partial hooks before Livewire registers its component lifecycle. */
trait WithNativeFilamentPartials
{
    protected function getPackageProviders($app): array
    {
        $providers = array_values(array_filter(parent::getPackageProviders($app), static fn (string $provider): bool => $provider !== SupportServiceProvider::class));
        $livewireIndex = array_search(LivewireServiceProvider::class, $providers, true);
        array_splice($providers, $livewireIndex === false ? 0 : $livewireIndex, 0, [SupportServiceProvider::class]);

        return $providers;
    }
}
