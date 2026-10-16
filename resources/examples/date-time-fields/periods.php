<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexYearPicker;

return [
    FlexMonthPicker::make('date_time__month')
        ->label('Month')
        ->displayFormat('F Y')
        ->default('2026-10')
        ->helperText('Stores YYYY-MM, without inventing a selected day.'),
    FlexYearPicker::make('date_time__year')
        ->label('Year')
        ->minValue('2020')
        ->maxValue('2030')
        ->default('2026')
        ->helperText('Only 2020–2030.'),
    FlexDurationField::make('date_time__duration')
        ->label('Elapsed duration')
        ->minValue('00:15')
        ->maxValue('100:00')
        ->durationStep(900)
        ->default('26:30')
        ->helperText('More than 24 hours; 15-minute step buttons.'),
];
