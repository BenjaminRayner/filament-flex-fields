<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\BubbleChoice\SelectionValue;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final class BubbleChoiceStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return $state === null ? [] : SelectionValue::canonicalize($state);
    }

    public function set(mixed $state): mixed
    {
        return $this->get($state);
    }
}
