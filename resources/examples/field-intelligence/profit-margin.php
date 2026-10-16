<?php

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

// Revenue and cost use the same unit: cents, as stored by CurrencyField.
$plan = FormulaEngine::plan(
    formulas: [
        'profit' => '{revenue}-{cost}',
        'margin' => 'if({revenue}>0,round({profit}/{revenue}*100,2),0)',
        'profitable' => '{profit}>0',
    ],
    inputs: [
        'revenue' => new NumericInput(min: 0, max: 1000000000, integer: true),
        'cost' => new NumericInput(min: 0, max: 1000000000, integer: true),
    ],
);

$plan->evaluate(['revenue' => 100000, 'cost' => 65000])->validated();
// profit: 35000 cents, margin: 35%, profitable: 1
