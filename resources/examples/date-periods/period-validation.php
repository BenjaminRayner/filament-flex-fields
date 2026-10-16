<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexYearPicker;

return [
    FlexMonthPicker::make('required_month')
        ->label('Required month')
        ->required(),
    FlexMonthPicker::make('validation_month')
        ->label('Bounded month')
        ->minValue('2026-01')
        ->maxValue('2026-12')
        ->default('2026-10'),
    FlexYearPicker::make('validation_year')
        ->label('Bounded year')
        ->minValue('2020')
        ->maxValue('2030')
        ->default('2026'),
];
