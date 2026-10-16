<?php

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;

$compiled = FormulaEngine::compileMap([
    'total' => '{price}*{quantity}',
    'progress' => 'if({goal}>0,{done}/{goal}*100,0)',
]);

$inputs = ['price' => 120, 'quantity' => 4, 'done' => 18, 'goal' => 24];
$previous = $compiled->evaluate($inputs);

// Previous outputs must come from your trusted server calculation.
$changed = $compiled->evaluate(
    ['price' => 150] + $inputs + $previous,
    changed: ['price'],
);
// ['total' => 600]; independent 'progress' is not recalculated.

// Without a complete previous result, the engine calculates the whole plan.
// For submitted form data, use a validated plan and CalculatedFields instead.
