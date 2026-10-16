<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeRangeField;

return [
    FlexDateRangeField::make('date_time__timed_range')
        ->label('Range with time')
        ->granularity('minute')
        ->hourCycle(12)
        ->rangePresets([])
        ->default(['start' => '2026-10-10T08:45:00', 'end' => '2026-10-15T18:45:00'])
        ->helperText('No shortcuts; independent start and end time.'),
    FlexTimeRangeField::make('date_time__time_range')
        ->label('Opening hours')
        ->hourCycle(24)
        ->hideTimeZone()
        ->minValue('08:00')
        ->maxValue('20:00')
        ->default(['start' => '09:00', 'end' => '17:00'])
        ->helperText('Both endpoints must fit within 08:00–20:00.'),
    FlexDatePicker::make('date_time__localized')
        ->label('Localized date')
        ->locale('pl')
        ->displayFormat('d F Y')
        ->weekStartsOn(1)
        ->default('2026-10-15')
        ->helperText('Polish labels; Monday is the first day of the week.'),
];
