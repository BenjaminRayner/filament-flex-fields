<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\TrafficSplit\Distribution;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final class TrafficSplitStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return Distribution::integers($state) ?? $state;
    }

    public function set(mixed $state): mixed
    {
        return Distribution::integers($state) ?? $state;
    }
}
