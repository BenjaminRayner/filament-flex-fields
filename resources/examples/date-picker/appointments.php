<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimePicker;
use Filament\Schemas\Components\Utilities\Get;

return [
    FlexDatePicker::make('arrival')
        ->label('Arrival')
        ->live()
        ->default('2026-10-10')
        ->helperText('Changing arrival updates the minimum departure date.'),
    FlexDatePicker::make('departure')
        ->label('Departure')
        ->minDate(fn (Get $get): ?string => $get('arrival'))
        ->default('2026-10-15')
        ->helperText('Departure cannot precede the selected arrival.'),
    FlexDateTimePicker::make('slots')
        ->label('Only appointment times')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->defaultTime('09:15')
        ->disabledWeekdays([0, 6])
        ->default('2026-10-15T11:40:00')
        ->helperText('Weekdays at 09:15, 11:40 or 15:10.'),
];
