<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PriceRangeField;

PriceRangeField::make('basic')
    ->label('Price range')
    ->variant('primary')
    ->max(2000)
    ->prefix('$')
    ->default([
        'min' => 100,
        'max' => 1124,
    ]);

PriceRangeField::make('hourly_budget')
    ->label('Hourly budget')
    ->min(1.25)
    ->max(250.25)
    ->step(0.25)
    ->integer(false)
    ->decimalPlaces(2)
    ->prefix('€')
    ->default(['min' => 25.5, 'max' => 100.75]);

PriceRangeField::make('budget_filter')
    ->label('Budget filter')
    ->showInputs(false)
    ->min(0)
    ->max(5000)
    ->step(50)
    ->default(['min' => 500, 'max' => 2500]);
