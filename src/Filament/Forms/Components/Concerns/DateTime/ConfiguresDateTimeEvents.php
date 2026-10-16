<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DateTime;

use Closure;

trait ConfiguresDateTimeEvents
{
    /** Register a PHP change callback through the standard field lifecycle. */
    public function onChange(Closure $callback): static
    {
        return $this->afterStateUpdated($callback);
    }
}
