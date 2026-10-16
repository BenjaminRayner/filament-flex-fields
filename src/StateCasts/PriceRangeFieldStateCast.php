<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\PriceRange\RangeValues;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final class PriceRangeFieldStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return RangeValues::pair($state) ?? $state;
    }

    public function set(mixed $state): mixed
    {
        return RangeValues::pair($state) ?? $state;
    }
}
