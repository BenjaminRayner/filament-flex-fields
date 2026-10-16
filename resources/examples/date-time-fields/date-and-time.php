<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimePicker;

return [
    FlexDateTimePicker::make('date_time__datetime')
        ->label('Date and time · 12h')
        ->hourCycle(12)
        ->default('2026-10-15T14:30:00')
        ->helperText('A calendar with its own time column.'),
    FlexDateTimePicker::make('date_time__datetime_seconds')
        ->label('Date and time with seconds')
        ->hourCycle(24)
        ->hideTimeZone()
        ->showSeconds()
        ->secondStep(5)
        ->default('2026-10-15T14:30:15')
        ->helperText('Second precision in both display and stored value.'),
    FlexDateTimePicker::make('date_time__appointment')
        ->label('Appointment slots')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->defaultTime('09:15')
        ->default('2026-10-15T11:40:00')
        ->helperText('Only 09:15, 11:40 and 15:10 can be accepted.'),
];
