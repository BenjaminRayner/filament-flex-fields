<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;
use Bjanczak\FilamentFlexFields\Enums\DateTimeGranularity;

class FlexDateTimePicker extends FlexCivilDateField
{
    public function getMode(): DateTimeFieldMode
    {
        return DateTimeFieldMode::DateTime;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->granularity = DateTimeGranularity::Minute;
        $this->closeOnSelect = false;
        $this->dateEditable = false;
        $this->dateClearable = true;
        $this->showCalendar = true;
        $this->showCalendarButton = true;
    }
}
