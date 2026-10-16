<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;

return [
    FlexMonthPicker::make('month')
        ->label('Month')
        ->displayFormat('F Y')
        ->locale('en')
        ->default('2026-10'),
    FlexMonthPicker::make('bounded_month')
        ->label('Months in 2026')
        ->minValue('2026-01')
        ->maxValue('2026-12')
        ->default('2026-10'),
    FlexMonthPicker::make('localized_month')
        ->label('Polski')
        ->locale('pl')
        ->displayFormat('F Y')
        ->default('2026-10'),
];
