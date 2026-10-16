<?php

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

// Validate syntax before evaluating an expression supplied by a user.
$expression = 'if({quantity}>0,{total}/{quantity},0)';
$syntaxErrors = FormulaEngine::validate($expression);

if ($syntaxErrors === []) {
    $plan = FormulaEngine::plan(
        formulas: ['unit_price' => $expression],
        inputs: [
            'quantity' => new NumericInput(min: 0, max: 1000, integer: true),
            'total' => new NumericInput(min: 0, max: 1000000),
        ],
    );
    $result = $plan->evaluate(['quantity' => 4, 'total' => 480]);
    $result->values; // ['unit_price' => 120]
    $result->errors; // []
}

FormulaEngine::explain($expression, ['quantity' => 4, 'total' => 480]);
// expression, references, substituted and result
