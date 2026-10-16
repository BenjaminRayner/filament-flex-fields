<?php

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

$plan = FormulaEngine::plan(
    formulas: [
        'subtotal' => '{price}*{quantity}',
        'discount' => 'round({subtotal}*pct({discount_rate}),2)',
        'total' => '{subtotal}-{discount}',
    ],
    inputs: [
        'price' => new NumericInput(min: 0, max: 100000),
        'quantity' => new NumericInput(min: 1, max: 1000, integer: true),
        'discount_rate' => new NumericInput(min: 0, max: 100),
    ],
);

$result = $plan->evaluate(['price' => 120, 'quantity' => 4, 'discount_rate' => 15]);
$totals = $result->validated(); // subtotal: 480, discount: 72, total: 408
