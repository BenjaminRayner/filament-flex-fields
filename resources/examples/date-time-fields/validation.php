<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexDatePicker::make('date_time__required')
        ->label('Required date')
        ->required()
        ->validationMessages(['required' => 'Choose an appointment date.']),
    FlexDatePicker::make('date_time__bounded')
        ->label('Working-day appointment')
        ->minDate('2026-10-01')
        ->maxDate('2026-10-31')
        ->disabledWeekdays([0, 6])
        ->disabledDates(['2026-10-16'])
        ->default('2026-10-15'),
    FlexTimeField::make('date_time__allowed')
        ->label('Restricted time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->default('11:40'),
];
