<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

return [
    FlexDateRangeField::make('bounded')
        ->label('October booking')
        ->minDate('2026-10-01')
        ->maxDate('2026-10-31')
        ->rangePresets([])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
    FlexDateRangeField::make('validation_same_day')
        ->label('At least two calendar days')
        ->allowSameDay(false)
        ->rangePresets([])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
    FlexDateRangeField::make('validation_time')
        ->label('Restricted endpoint times')
        ->granularity('minute')
        ->hourCycle(24)
        ->hideTimeZone()
        ->rangePresets([])
        ->allowedTimes(['09:00', '12:00', '17:00'])
        ->default(['start' => '2026-10-10T09:00:00', 'end' => '2026-10-15T17:00:00']),
];
