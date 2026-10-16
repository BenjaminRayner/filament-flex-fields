<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\SwitchField\BooleanValue;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final class SwitchStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return BooleanValue::normalize($state);
    }

    public function set(mixed $state): mixed
    {
        return BooleanValue::normalize($state);
    }
}
