<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

final class FlexFieldsPlaygroundStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $slug): ?array
    {
        $stored = Cache::get($this->key($slug));

        return is_array($stored) ? $stored : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function put(string $slug, array $state): void
    {
        Cache::put($this->key($slug), $state, now()->addDays(30));
    }

    public function forget(string $slug): void
    {
        Cache::forget($this->key($slug));
    }

    public function key(string $slug): string
    {
        $panel = Filament::getCurrentPanel();
        $tenant = Filament::getTenant();
        $scope = json_encode([
            $panel?->getId(), $panel?->getAuthGuard(),
            $tenant?->getMorphClass(), $tenant?->getKey(), Auth::guard($panel?->getAuthGuard())->id() ?? 'guest', $slug,
        ], JSON_THROW_ON_ERROR);

        return 'flex.catalog.demo.'.hash('sha256', $scope);
    }
}
