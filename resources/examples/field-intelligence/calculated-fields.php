<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\CalculatedFields;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

$plan = FormulaEngine::plan(
    formulas: ['total' => 'round({price}*{quantity},2)'],
    inputs: [
        'price' => new NumericInput(min: 0, max: 100000),
        'quantity' => new NumericInput(min: 1, max: 1000, integer: true),
    ],
);

$fields = (new CalculatedFields($plan))->bind([
    'price' => FlexTextInput::make('price')->label('Price')->numeric()->default(120),
    'quantity' => FlexTextInput::make('quantity')->label('Quantity')->numeric()->default(4),
    'total' => FlexTextInput::make('total')->label('Total'),
]);

// Use $fields in your Filament schema.
// Input rules are applied on the server; changes update the disabled total.
// Form dehydration recalculates total from validated inputs before saving.
// Each embedded field retains its own FLEX rendering and assets.
