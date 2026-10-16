<?php

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

$plan = FormulaEngine::plan(
    formulas: [
        'progress' => 'if({goal}>0,clamp(round({done}/{goal}*100,2),0,100),0)',
        'remaining' => 'max({goal}-{done},0)',
    ],
    inputs: [
        'done' => new NumericInput(min: 0, max: 1000000, integer: true),
        'goal' => new NumericInput(min: 0, max: 1000000, integer: true),
    ],
);

$plan->evaluate(['done' => 18, 'goal' => 24])->validated(); // progress: 75, remaining: 6
$plan->evaluate(['done' => 18, 'goal' => 0])->validated(); // progress: 0, remaining: 0
