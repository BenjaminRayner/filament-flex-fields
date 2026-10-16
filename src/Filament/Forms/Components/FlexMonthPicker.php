<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;

class FlexMonthPicker extends FlexPeriodPicker
{
    public function getMode(): DateTimeFieldMode
    {
        return DateTimeFieldMode::Month;
    }
}
