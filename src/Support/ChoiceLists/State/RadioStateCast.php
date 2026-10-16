<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceLists\State;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** Transport numeric identities as strings without JavaScript precision loss. */
final class RadioStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return is_int($state) ? (string) $state : $state;
    }

    public function set(mixed $state): mixed
    {
        return $this->get($state);
    }
}
