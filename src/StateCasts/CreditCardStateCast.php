<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\CreditCard\CardValues;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final class CreditCardStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return CardValues::acceptsFormatting($state) ? CardValues::preview($state) : $state;
    }

    public function set(mixed $state): mixed
    {
        return $this->get($state);
    }
}
