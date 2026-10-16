<?php

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

$plan = FormulaEngine::plan(
    formulas: [
        'subtotal' => '{price}*{quantity}',
        'tax' => 'round({subtotal}*pct({tax_rate}),2)',
        'total' => '{subtotal}+{tax}',
    ],
    inputs: [
        'price' => new NumericInput(min: 0, max: 100000),
        'quantity' => new NumericInput(min: 1, max: 1000, integer: true),
        'tax_rate' => new NumericInput(min: 0, max: 100),
    ],
    allowedFunctions: ['round', 'pct'],
);

$result = $plan->evaluate(['price' => 120, 'quantity' => 4, 'tax_rate' => 23]);
$result->successful(); // true
$result->values; // subtotal: 480, tax: 110.4, total: 590.4
$result->errors; // []

// Before saving: invalid inputs raise Laravel's ValidationException.
$valuesForStorage = $result->validated();

// Only inputs are read. A submitted 'total' => 999999 is ignored and recalculated.
// Optional fields can define a validated default:
$optional = new NumericInput(min: 0, max: 100, required: false, default: 0);
