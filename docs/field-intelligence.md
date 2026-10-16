---
title: Calculated formulas
description: Build validated, live calculations for Filament forms with dependency ordering, function policies and results recalculated before saving.
icon: square-function
---

Turn prices, quantities, percentages and other numeric inputs into values that stay in sync. FLEX v4 provides reusable calculation plans, live field bindings and server-side validation.

<CardGroup cols={2}>
  <Card title="Bind a form" icon="text-cursor-input" href="#live-calculated-fields">
    Keep calculated fields current and recalculate them before saving.
  </Card>
  <Card title="Calculate anywhere" icon="square-function" href="#validated-calculation-plans">
    Reuse the same rules in services, actions and form workflows.
  </Card>
</CardGroup>

## Validated calculation plans

Declare the inputs a calculation accepts and the outputs it produces. Every input is validated before any result is returned. A failed calculation returns errors and no partial output.

```php
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

$result = $plan->evaluate([
    'price' => 120,
    'quantity' => 4,
    'tax_rate' => 23,
]);

$result->successful(); // true
$result->values;       // subtotal: 480, tax: 110.4, total: 590.4
$result->errors;       // []
$valuesToSave = $result->validated();
```

`validated()` returns the calculated values or throws Laravel's `ValidationException`. Submitted values such as `total => 999999` are ignored: outputs are always calculated from the declared inputs.

### Input options

| Option | Default | Behavior |
| --- | --- | --- |
| `min` | `null` | Inclusive minimum; `null` removes the lower bound. |
| `max` | `null` | Inclusive maximum; `null` removes the upper bound. |
| `integer` | `false` | Require a whole number within ±9,007,199,254,740,991. Fractional text is checked before floating-point conversion. |
| `required` | `true` | Reject missing or empty input. |
| `default` | `0` | Value used for missing or empty optional input. It must satisfy the same bounds and integer rule. |

```php
$discount = new NumericInput(
    min: 0,
    max: 100,
    required: false,
    default: 0,
);
```

Zero is a value, not an empty input. Arrays, booleans, nonnumeric strings, infinity and numeric overflow are rejected. Numeric strings submitted by form controls are accepted.

### Function policies

Omit `allowedFunctions` to enable the supported whitelist. Supply a subset to restrict a particular plan, or `[]` to allow only arithmetic and comparisons. Unknown functions and undeclared input references are rejected when constructing the plan.

## Live calculated fields

Bind a plan to your own FLEX fields. Configure labels, appearance, sizes, defaults and field-specific behavior on those fields as usual.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\CalculatedFields;

$fields = (new CalculatedFields($plan))->bind([
    'price' => FlexTextInput::make('price')
        ->label('Unit price')
        ->numeric()
        ->default(120),
    'quantity' => FlexTextInput::make('quantity')
        ->numeric()
        ->default(4),
    'tax_rate' => FlexTextInput::make('tax_rate')
        ->numeric()
        ->suffix('%')
        ->default(23),
    'subtotal' => FlexTextInput::make('subtotal')->label('Subtotal'),
    'tax' => FlexTextInput::make('tax')->label('Tax'),
    'total' => FlexTextInput::make('total')->label('Total'),
]);

// Use $fields in your Filament schema.
```

<Steps>
  <Step title="Fill">
    Calculated fields are recomputed from current inputs when the form is filled, including when editing a record.
  </Step>
  <Step title="Change">
    Inputs update live with a 250 ms debounce. Invalid input clears calculated previews until the input becomes valid.
  </Step>
  <Step title="Save">
    Input rules run on the server. During form dehydration, outputs are calculated again; a manipulated preview cannot become the saved result.
  </Step>
</Steps>

`bind()` owns input hydration and calculated-field hydration, validation, save hooks and disabled state. Apply it after configuring your fields. Additional input update callbacks and validation rules are preserved. Do not replace the calculated-field hooks or enable the calculated fields afterward.

### Keep your own input callbacks

Use `schema()` when your inputs already have `afterStateHydrated()` callbacks. It returns a layout group that refreshes calculated previews after every input has hydrated. The input callbacks remain intact, including when outputs appear before their inputs.

Prefer `schema()` for large calculations: it refreshes the complete group once after initialization rather than refreshing after each input. `bind()` remains useful when you need a flat list of fields.

```php
$plan = FormulaEngine::plan(['total' => '{price}*{quantity}'], [
    'price' => new NumericInput(min: 0, max: 10000),
    'quantity' => new NumericInput(min: 1, max: 100, integer: true),
]);

$group = (new CalculatedFields($plan))->schema([
    'price' => FlexTextInput::make('price')
        ->numeric()
        ->afterStateHydrated(function (FlexTextInput $component) {
            // Apply your application's initial-value policy here.
            $component->state($component->getState() ?? 0);
        }),
    'quantity' => FlexTextInput::make('quantity')->numeric()->default(1),
    'total' => FlexTextInput::make('total'),
])->statePath('invoice');

// Add $group to your form schema.
```

If a custom update callback changes another input, request that input's update hooks:

```php
function (\Filament\Schemas\Components\Utilities\Set $set) {
    $set('quantity', 3, shouldCallUpdatedHooks: true);
}
```

Normalize inputs before validation. Callbacks that change input values after validation, replace calculated-field hooks or mutate the final saved results are your application's responsibility. For a custom persistence workflow, evaluate and validate the final input data immediately before writing it.

<Note>
  Save through the normal Filament form lifecycle (`getState()`). Directly saving raw Livewire state bypasses form validation and dehydration. In a custom save action, call the plan's `evaluate(...)->validated()` before persisting results.
</Note>

### Repeater rows and relationships

Every row calculates its own inputs; changing, reordering or removing one row does not reuse another row's result. The same binding can be used in a JSON repeater or a `HasMany` relationship repeater:

```php
\Filament\Forms\Components\Repeater::make('lines')
    ->schema((new CalculatedFields($plan))->bind([
        'price' => FlexTextInput::make('price')->numeric(),
        'quantity' => FlexTextInput::make('quantity')->numeric(),
        'total' => FlexTextInput::make('total'),
    ]));

// For an Eloquent relationship, add ->relationship('lines').
```

Invalid numeric inputs and runtime errors, such as division by zero, fail form validation before relationship changes are saved. Invalid previews remain empty so the form can still be displayed. Use a database transaction around the complete save when parent records, related records or application callbacks must commit together. For a custom create workflow, create the parent first, assign it to the form and then save its relationships.

### Multiple and nested calculations

A flat name prefix separates independent bindings in one schema. Array keys remain the plan's input/output names; field names include the prefix.

```php
$fields = (new CalculatedFields($plan, prefix: 'invoice_'))->bind([
    'price' => FlexTextInput::make('invoice_price')->numeric(),
    'quantity' => FlexTextInput::make('invoice_quantity')->numeric(),
    'tax_rate' => FlexTextInput::make('invoice_tax_rate')->numeric(),
    'subtotal' => FlexTextInput::make('invoice_subtotal'),
    'tax' => FlexTextInput::make('invoice_tax'),
    'total' => FlexTextInput::make('invoice_total'),
]);
```

For a nested schema, use its `statePath()` and bind locally named fields inside it. The parent schema owns the nesting; do not put a dotted state path in the binding's prefix. Each group reads its own inputs.

## Money and percentages

`CurrencyField` stores amounts in minor units: €100.00 is `10000` cents. Keep inputs and outputs in the same unit.

```php
$taxPlan = FormulaEngine::plan(
    formulas: [
        'tax' => 'round({subtotal}*pct({tax_rate}),0)',
        'total' => '{subtotal}+{tax}',
    ],
    inputs: [
        'subtotal' => new NumericInput(min: 0, max: 1000000000, integer: true),
        'tax_rate' => new NumericInput(min: 0, max: 100),
    ],
);

$taxPlan->evaluate(['subtotal' => 10000, 'tax_rate' => 23])->validated();
// tax: 2300 cents; total: 12300 cents

FormulaEngine::moneyMajor(12300); // 123
FormulaEngine::moneyMinor(123);   // 12300
FormulaEngine::moneyMinor(123, fractionDigits: 3); // 123000
```

Money helpers accept `fractionDigits` from 0 to 6. Minor-unit inputs and rounded minor-unit outputs beyond ±9,007,199,254,740,991 are rejected to prevent silent integer precision loss. Calculations use PHP floating-point arithmetic: decimal values such as `0.1 + 0.2` can carry a rounding difference. Choose explicit rounding and bounded inputs for your application; this is not an arbitrary-precision accounting engine.

## Common calculations

<Tabs>
  <Tab title="Progress">
    ```php
    FormulaEngine::evaluate(
        'if({goal}>0,clamp(round({done}/{goal}*100,2),0,100),0)',
        ['done' => 18, 'goal' => 24],
    ); // 75
    ```
  </Tab>
  <Tab title="Margin">
    ```php
    FormulaEngine::evaluateMap([
        'profit' => '{revenue}-{cost}',
        'margin' => 'if({revenue}>0,round({profit}/{revenue}*100,2),0)',
    ], ['revenue' => 1000, 'cost' => 650]);
    // profit: 350; margin: 35
    ```
  </Tab>
  <Tab title="Stock replenishment">
    ```php
    FormulaEngine::evaluateMap([
        'available' => 'max({stock}-{reserved},0)',
        'shortfall' => 'max({target}-{available},0)',
        'packs' => 'ceil({shortfall}/{pack_size})',
    ], ['stock' => 40, 'reserved' => 15, 'target' => 80, 'pack_size' => 10]);
    // available: 25; shortfall: 55; packs: 6
    ```
  </Tab>
  <Tab title="Volume discount">
    ```php
    FormulaEngine::evaluateMap([
        'discount_rate' => 'if({quantity}>=20,15,if({quantity}>=10,10,0))',
        'total' => 'round({price}*{quantity}*(1-pct({discount_rate})),2)',
    ], ['price' => 120, 'quantity' => 20]);
    // discount_rate: 15; total: 2040
    ```
  </Tab>
</Tabs>

For values submitted by a user, declare the corresponding `NumericInput` contracts in a plan before using these expressions. Direct `evaluate()` and `evaluateMap()` are lower-level APIs for values whose application rules you already validate.

## Formula language

References use `{field_name}`. Names begin with a letter or underscore and contain only letters, digits and underscores. Calculation input/output names have a 128-character limit. Use decimal literals, parentheses and these operators:

| Purpose | Operators |
| --- | --- |
| Arithmetic | `+`, `-`, `*`, `/`, `%` and unary `-` |
| Comparisons | `>`, `<`, `>=`, `<=`, `==`, `!=` |

Comparisons return `1` or `0`; zero is false and nonzero is true. Division and modulo by zero are errors. Strings, arbitrary PHP, property access and function calls outside the whitelist are not supported.

### Supported functions

| Function | Arguments and behavior |
| --- | --- |
| `abs(x)` | Absolute value. |
| `floor(x)`, `ceil(x)`, `trunc(x)` | Round down, round up, or truncate toward zero. |
| `round(x, precision)` | Optional integer precision from 0 to 12; default 0. |
| `min(a,b,...)`, `max(a,b,...)` | Minimum or maximum; at least two arguments. |
| `sum(a,b,...)`, `avg(a,b,...)` | Sum or average; at least two arguments. |
| `clamp(x,min,max)` | Restrict to inclusive bounds; minimum must not exceed maximum. |
| `pct(x)` | Divide a percentage by 100. |
| `pow(base,exponent)` | Exponent must be between -12 and 12. |
| `sqrt(x)` | Square root; negative values are rejected. |
| `sign(x)` | `-1`, `0` or `1`. |
| `between(x,min,max)` | `1` if within inclusive bounds, otherwise `0`. |
| `if(condition,yes,no)` | Evaluate only the selected branch. |
| `and(a,b,...)`, `or(a,b,...)` | At least two arguments; stop when the outcome is known. |
| `not(x)` | `1` when x is zero, otherwise `0`. |
| `coalesce(a,b,...)`, `nz(a,b,...)` | First nonzero value, or zero if all are zero. At least one argument. These are numeric helpers, not SQL null-coalescing. |

Argument counts are checked even inside branches that will not execute. Valid unused branches remain lazy: `if(1,20,10/0)` returns `20`.

## Dependencies, cycles and incremental updates

A plan orders outputs by dependency. A cyclic graph is rejected before evaluation.

```php
$plan->order();        // subtotal, tax, total
$plan->dependencies(); // references for each output

FormulaEngine::detectCycle([
    'a' => '{b}',
    'b' => '{c}',
    'c' => '{a}',
]); // ['a', 'b', 'c']
```

For a trusted server-side workflow, compile a map once and reuse it. Incremental evaluation returns only affected outputs when a complete previous result is available.

```php
$compiled = FormulaEngine::compileMap([
    'total' => '{price}*{quantity}',
    'progress' => 'if({goal}>0,{done}/{goal}*100,0)',
]);
$inputs = ['price' => 120, 'quantity' => 4, 'done' => 18, 'goal' => 24];
$previous = $compiled->evaluate($inputs);
$changed = $compiled->evaluate(
    ['price' => 150] + $inputs + $previous,
    changed: ['price'],
); // ['total' => 600]
```

Without a complete prior result, the map performs a full evaluation. Do not use client-submitted previous outputs as an incremental baseline. Use a validated full plan for authoritative saves.

## Inspect expressions and handle errors

```php
FormulaEngine::validate('{price}*{quantity}'); // [] means valid syntax
FormulaEngine::fieldReferences('{price}*{quantity}'); // price, quantity
FormulaEngine::functionsUsed('round({price},2)'); // round
FormulaEngine::allowedFunctions(); // supported whitelist
FormulaEngine::explain('{price}*{quantity}', ['price' => 120, 'quantity' => 4]);
// expression, references, substituted and result

$invalid = $plan->evaluate(['price' => -1, 'quantity' => 0, 'tax_rate' => 23]);
$invalid->successful(); // false
$invalid->values;       // []
$invalid->errors;       // errors keyed by input name
```

For syntax or configuration errors, direct engine methods throw `InvalidArgumentException`. `tryEvaluate()` returns `null` if evaluation fails. Use it for optional display values; do not replace required validation with a silent fallback.

Expressions are bounded to 8192 bytes, 2048 syntax nodes and 128 levels of syntax depth. A plan accepts at most 512 inputs and 512 calculated outputs. Non-finite intermediate results are rejected. Compiled syntax is reused in a bounded cache; submitted input values and results are not stored in that cache.

## Explore in the playground

Open **Calculated formulas** to try five business scenarios, write expressions, inspect dependencies, compare incremental updates, test malformed inputs and use real bound fields. The code examples include complete input contracts and form bindings.

<CardGroup cols={2}>
  <Card title="Currency field" icon="coins" href="/docs/currencyfield">
    Format amounts stored in minor units.
  </Card>
  <Card title="Flex Field Groups" icon="layers" href="/docs/flex-field-groups">
    Configure calculated values in dynamic form schemas.
  </Card>
</CardGroup>
