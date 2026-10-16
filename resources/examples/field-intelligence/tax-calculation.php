<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\CalculatedFields;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

// Amounts remain in cents throughout this plan.
$plan = FormulaEngine::plan(
    formulas: [
        'tax' => 'round({subtotal}*pct({tax_rate}),0)',
        'total' => '{subtotal}+{tax}',
    ],
    inputs: [
        'subtotal' => new NumericInput(min: 0, max: 1000000000, integer: true),
        'tax_rate' => new NumericInput(min: 0, max: 100),
    ],
);

$fields = (new CalculatedFields($plan))->bind([
    'subtotal' => CurrencyField::make('subtotal')->currency('EUR')->default(10000),
    'tax_rate' => NumberStepper::make('tax_rate')->suffix('%')->default(23),
    'tax' => CurrencyField::make('tax')->currency('EUR'),
    'total' => CurrencyField::make('total')->currency('EUR'),
]);
