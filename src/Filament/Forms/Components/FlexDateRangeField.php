<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DateTime\ConfiguresDateRange;

class FlexDateRangeField extends FlexCivilDateField
{
    use ConfiguresDateRange;

    public function getMode(): DateTimeFieldMode
    {
        return DateTimeFieldMode::DateRange;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dateEditable = false;
        $this->fixedCalendarWeeks = false;
        $this->showCalendar = true;
        $this->showCalendarButton = true;
        $this->closeOnSelect = false;
    }
}
