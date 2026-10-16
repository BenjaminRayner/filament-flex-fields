<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\Slider\SliderPolicy;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** Preserve invalid payloads so the server rejects them rather than coercing to zero. */
final class FlexSliderStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        if (is_array($state)) {
            return array_map(fn (mixed $value): mixed => SliderPolicy::number($value) ?? $value, $state);
        }

        return SliderPolicy::number($state) ?? $state;
    }

    public function set(mixed $state): mixed
    {
        return $this->get($state);
    }
}
