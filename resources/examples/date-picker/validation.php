<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;

return [
    FlexDatePicker::make('required')
        ->label('Required appointment')
        ->required()
        ->validationMessages(['required' => 'Choose an appointment date.']),
    FlexDatePicker::make('validation_bounds')
        ->label('Limited appointment')
        ->minDate('2026-10-01')
        ->maxDate('2026-10-31')
        ->default('2026-10-15'),
    FlexDatePicker::make('validation_blackout')
        ->label('Blackout validation')
        ->disabledDates(['2026-10-16'])
        ->disabledWeekdays([0, 6])
        ->default('2026-10-15'),
];
