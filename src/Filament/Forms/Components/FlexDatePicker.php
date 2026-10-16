<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;

class FlexDatePicker extends FlexCivilDateField
{
    public function getMode(): DateTimeFieldMode
    {
        return DateTimeFieldMode::Date;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dateEditable = false;
        $this->dateClearable = true;
        $this->showCalendar = true;
        $this->showCalendarButton = true;
    }
}
