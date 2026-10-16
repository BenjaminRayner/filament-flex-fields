<?php

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;

FormulaEngine::detectCycle([
    'net' => '{total}-100',
    'tax' => '{net}*0.23',
    'total' => '{net}+100',
]); // ['net', 'total'] — tax depends on the cycle but is not part of it.

// A valid graph is evaluated in dependency order, regardless of declaration order.
$compiled = FormulaEngine::compileMap([
    'total' => '{net}+{tax}',
    'tax' => '{net}*0.23',
    'net' => '{price}*{quantity}',
]);
$compiled->computedKeys(); // ['net', 'tax', 'total']
$compiled->evaluate(['price' => 120, 'quantity' => 4]);
