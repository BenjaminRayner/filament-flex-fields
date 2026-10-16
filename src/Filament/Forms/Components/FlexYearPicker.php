<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;

class FlexYearPicker extends FlexPeriodPicker
{
    public function getMode(): DateTimeFieldMode
    {
        return DateTimeFieldMode::Year;
    }
}
