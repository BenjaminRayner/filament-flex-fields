<?php

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

$plan = FormulaEngine::plan(
    formulas: ['score' => 'clamp(round({value},0),0,100)'],
    inputs: ['value' => new NumericInput(min: -1000, max: 1000)],
    allowedFunctions: ['round', 'clamp'],
);

$plan->evaluate(['value' => 103.7])->validated(); // ['score' => 100]
FormulaEngine::allowedFunctions(); // Complete supported whitelist.
FormulaEngine::validate('sin(1)'); // Rejected: function is not supported.
FormulaEngine::validate('if(1,10,pct(1,2))'); // Rejected: incorrect signature, even in an unused branch.

// An empty function policy allows arithmetic and comparisons, but no helpers.
$arithmeticOnly = FormulaEngine::plan(
    formulas: ['double' => '{value}*2'],
    inputs: ['value' => new NumericInput(min: 0, max: 100)],
    allowedFunctions: [],
);
