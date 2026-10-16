<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;

NumberStepper::make('nights')
    ->label('Nights')
    ->minValue(1)
    ->maxValue(30)
    ->live(debounce: 250);

NumberStepper::make('price')
    ->prefix('$')
    ->decimalPlaces(2)
    ->integer(false)
    ->step(0.5);

NumberStepper::make('seats')
    ->label('Seats')
    ->minValue(1)
    ->maxValue(5)
    ->default(1)
    ->locale('en')
    ->suffixForms([
        'one' => 'seat',
        'other' => 'seats',
    ]);
