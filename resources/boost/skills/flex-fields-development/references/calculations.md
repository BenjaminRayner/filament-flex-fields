# Calculations: explicit inputs and server results

Use a `FormulaEngine` plan with declared numeric inputs. Bind through
`CalculatedFields` for form integration; calling the standalone engine alone does
not install a save hook on an arbitrary form.

```php
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

$plan = FormulaEngine::plan(
    formulas: ['total' => 'round({price}*{quantity},2)'],
    inputs: [
        'price' => new NumericInput(min: 0, max: 100000),
        'quantity' => new NumericInput(min: 1, max: 1000, integer: true),
    ],
    allowedFunctions: ['round'],
);

$values = $plan->evaluate([
    'price' => 120,
    'quantity' => 4,
    'total' => 1,
])->validated(); // total is 480; the submitted output is not trusted.
```

The application owns authorization, financial domain rules and persistence.
Check rounding/currency requirements before using generic numeric calculations
for money. Validate inputs, recalculate on the server and only then persist the
result. Reuse the same plan in services/actions rather than maintaining an
unrelated JavaScript-only formula. Test forged output and invalid inputs, not
only the happy-path number shown by the browser.

Guide: https://flex-fields.bjanczak.com/docs/field-intelligence
