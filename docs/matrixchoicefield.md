---
title: "MatrixChoiceField"
description: "Build configurable choice matrices for Filament forms with row and column options, selection rules and server-side validation."
icon: "grid-2x2-check"
"canonical": "https://flex-fields.bjanczak.com/docs/matrixchoicefield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/matrix-choice.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "MatrixChoiceField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/matrix-choice-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/matrix-choice-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/matrix-choice.webp"
"twitter:image:alt": "MatrixChoiceField in the FLEX Fields playground"
---

{/* flex-component-media:start */}
<script type="application/ld+json">
{JSON.stringify({
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://flex-fields.bjanczak.com/#organization",
      "name": "FLEX Fields",
      "url": "https://flex-fields.bjanczak.com/",
      "logo": "https://flex-fields.bjanczak.com/art/docs-logo-light.svg",
      "sameAs": [
        "https://github.com/janczakb/filament-flex-fields"
      ]
    },
    {
      "@type": "WebSite",
      "@id": "https://flex-fields.bjanczak.com/#website",
      "name": "FLEX Fields",
      "url": "https://flex-fields.bjanczak.com/",
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "inLanguage": "en"
    },
    {
      "@type": "WebPage",
      "@id": "https://flex-fields.bjanczak.com/docs/matrixchoicefield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/matrixchoicefield",
      "name": "MatrixChoiceField",
      "description": "Build configurable choice matrices for Filament forms with row and column options, selection rules and server-side validation.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/matrixchoicefield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/matrixchoicefield#article",
      "url": "https://flex-fields.bjanczak.com/docs/matrixchoicefield",
      "headline": "MatrixChoiceField",
      "description": "Build configurable choice matrices for Filament forms with row and column options, selection rules and server-side validation.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/matrixchoicefield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/matrix-choice-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/matrix-choice-motion.mp4#video",
      "name": "MatrixChoiceField — FLEX Fields demonstration",
      "description": "A recorded demonstration of MatrixChoiceField in the FLEX Fields playground for Filament. Build configurable choice matrices for Filament forms with row and column options, selection rules and server-side validation.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/matrix-choice.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.7S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/matrix-choice-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/matrixchoicefield#article"
      }
    }
  ]
}).replace(/</g, '\u003c')}
</script>

<video
  autoPlay
  muted
  loop
  playsInline
  controls
  preload="metadata"
  className="w-full aspect-video rounded-xl"
  src="/art/previews/matrix-choice-motion.mp4"
  poster="/art/previews/matrix-choice.webp"
  width="1920"
  height="1080"
  aria-label="MatrixChoiceField — FLEX Fields demonstration"
>
  <a href="/art/previews/matrix-choice-motion.mp4">Watch the MatrixChoiceField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)


### Summary

**Multiple choice grid** (matrix / survey table): row labels on the left, column headers on top, radio or checkbox in each cell. Gray inset frame with white body panel. Per-row validation and **reactive conditional disabling** (no `live()` required).

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\MatrixChoiceField` |
| **State type** | Radio: `array&lt;string, string\|null&gt;` · Checkbox: `array&lt;string, list&lt;string&gt;&gt;` |
| **FieldType** | `matrix_choice` |
| **Playground** | `matrix-choice` |
| **Stylesheet** | Lazy `matrix-choice-field` bundle |
| **Model cast** | `'responses' =&gt; 'array'` or `'responses' =&gt; 'json'` |

> Use `matrixColumns()` — **not** `columns()` — because `columns()` is reserved by Filament layout grids.

### Full example

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MatrixChoiceField;

MatrixChoiceField::make('feature_priorities')
    ->label('Feature priorities')
    ->helperText('Assign priority per feature. Dark mode High blocks CSV High.')
    ->mode('checkbox')
    ->size('md')
    ->color('primary')
    ->rows([
        'dark_mode' => [
            'label' => 'Dark mode',
            'description' => 'UI theme support',
            'required' => true,
            'max_selections' => 1,
        ],
        'csv_export' => [
            'label' => 'CSV export',
            'min_selections' => 1,
            'max_selections' => 2,
        ],
        'api_access' => [
            'label' => 'API access',
            'disabled' => true,
        ],
    ])
    ->matrixColumns([
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => [
            'label' => 'High',
            'icon' => 'heroicon-o-bolt',
        ],
    ])
    ->requiredRows(['dark_mode'])
    ->disabledCells([
        // Static: always lock CSV → Low (example)
        // 'csv_export' => ['low'],
    ])
    ->disableCellWhen('csv_export', 'high', 'dark_mode', 'high')
    ->disableRowWhen('api_access', 'dark_mode', 'low')
    ->default([
        'dark_mode' => ['high'],
        'csv_export' => ['medium'],
    ]);
```

Radio mode (one answer per row — survey / mood matrix):

```php
MatrixChoiceField::make('mood')
    ->label('Tell us about your mood')
    ->mode('radio')
    ->rows([
        'saturday' => ['label' => 'Saturday', 'required' => true],
        'sunday' => ['label' => 'Sunday', 'required' => true],
        'monday' => 'Monday',
    ])
    ->matrixColumns([
        'happy' => 'Happy',
        'neutral' => 'Neutral',
        'sad' => 'Sad',
        'pleading' => 'Pleading',
        'party' => 'Party',
        'zany' => 'Zany',
    ])
    ->default([
        'saturday' => 'happy',
        'sunday' => 'neutral',
    ]);
```

### Row option shape

Each key in `rows()` is stored in the database. Value can be a plain string (used as label) or a rich array:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `label` | `string` | row key | Left column row title |
| `description` / `desc` | `string\|null` | `null` | Optional subtitle under row label |
| `required` | `bool` | `false` | Row must have at least one selection. Overrides `requiredRows()` when set explicitly |
| `disabled` | `bool` | `false` | Entire row locked (all cells disabled) |
| `min_selections` / `min` | `int\|null` | `null` | Checkbox only — minimum selected columns in this row |
| `max_selections` / `max` | `int\|null` | `null` | Checkbox only — maximum selected columns in this row |

```php
->rows([
    'billing' => 'Billing', // shorthand for ['label' => 'Billing']
    'shipping' => [
        'label' => 'Shipping',
        'description' => 'Delivery options',
        'required' => true,
        'min_selections' => 1,
        'max_selections' => 2,
    ],
])
```

### Column option shape

Each key in `matrixColumns()` is a selectable column id (stored in state).

| Form | Example |
|------|---------|
| `key =&gt; 'Label'` | `'happy' =&gt; 'Happy'` |
| Rich array | `'high' =&gt; ['label' =&gt; 'High', 'icon' =&gt; 'heroicon-o-bolt', 'disabled' =&gt; true]` |

| Key | Type | Description |
|-----|------|-------------|
| `label` | `string` | Header text (or emoji) shown above cells |
| `icon` | `string\|null` | Optional Heroicon above label (alternative to `columnIcons()`) |
| `disabled` | `bool` | Disables this column in **every** row |

```php
->matrixColumns([
    'low' => 'Low',
    'high' => ['label' => 'High', 'icon' => 'heroicon-o-fire'],
])
->columnIcons([
    'low' => 'heroicon-o-arrow-down',
    'high' => 'heroicon-o-arrow-up',
]);
```

### State format

**Radio mode** — one column key per row (or omitted if empty):

```json
{
  "saturday": "happy",
  "sunday": "neutral"
}
```

**Checkbox mode** — list of column keys per row:

```json
{
  "dark_mode": ["high"],
  "csv_export": ["medium", "low"]
}
```

- Default state: `[]`
- On form save, unknown rows, invalid column keys and malformed selections fail validation. Empty rows are omitted from the saved value after successful validation.
- Use Eloquent cast `'field' =&gt; 'array'` or `'field' =&gt; 'json'`

### Validation

#### Built-in (per row)

| Rule | Radio | Checkbox | Detail |
|------|-------|----------|--------|
| `required` on row | ✓ | ✓ | Row must have a selection |
| `requiredRows([...])` | ✓ | ✓ | Mark rows required by key |
| `required()` on field | ✓ | ✓ | All non-disabled rows required when no `requiredRows()` set |
| `min_selections` | — | ✓ | Min columns selected in row |
| `max_selections` | — | ✓ | Max columns selected in row |
| Static `disabled` / `disabledRows` | ✓ | ✓ | Selection in locked row fails |
| `disabledCells` | ✓ | ✓ | Selection in locked cell fails |
| `disableCellWhen` / `disableRowWhen` | ✓ | ✓ | Same rules enforced server-side |

Translation keys (`resources/lang/en/default.php`):

| Key | When |
|-----|------|
| `validation.matrix_choice.invalid` | State is not an array |
| `validation.matrix_choice.invalid_option` | Unknown or disabled column selected |
| `validation.matrix_choice.row_required` | Required row empty (`:row`) |
| `validation.matrix_choice.row_min` | Too few selections (`:row`, `:count`) |
| `validation.matrix_choice.row_max` | Too many selections (`:row`, `:count`) |

#### Custom cross-row rules

Use standard Filament `-&gt;rule()` for business logic across rows:

```php
use Closure;

MatrixChoiceField::make('features')
    ->mode('checkbox')
    ->rows([...])
    ->matrixColumns([...])
    ->rule(function (): Closure {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $value = is_array($value) ? $value : [];

            if (in_array('high', $value['dark_mode'] ?? [], true)
                && in_array('high', $value['csv_export'] ?? [], true)) {
                $fail('High priority can only be assigned to one feature.');
            }
        };
    });
```

### Configuration API

#### `mode('radio'|'checkbox')`


| Value | Behaviour |
|-------|-------------|
| `radio` (default) | Exactly one column per row |
| `checkbox` | Zero or more columns per row |

```php
->mode('radio')    // survey grid
->mode('checkbox') // multi-tag per row
```

#### `rows(array|Closure $rows)`


Row definitions — see [Row option shape](#row-option-shape). Accepts `Closure` for dynamic rows.

```php
MatrixChoiceField::make('field_name')
    ->rows(['value1', 'value2']);
```
#### `matrixColumns(array|Closure $columns)`


Column headers — see [Column option shape](#column-option-shape).

```php
MatrixChoiceField::make('field_name')
    ->matrixColumns(['value1', 'value2']);
```
#### `columnIcons(array|Closure $icons)`


Per-column icon map merged into column metadata:

```php
->columnIcons([
    'happy' => 'heroicon-o-face-smile',
    'sad' => 'heroicon-o-face-frown',
])
```

#### `requiredRows(array|Closure $keys)`


Mark rows as required without inline `required =&gt; true`:

```php
->requiredRows(['saturday', 'sunday'])
```

#### `disabledRows(array|Closure $keys)`


Lock entire rows by key (static, always on):

```php
->disabledRows(['archived_feature', 'legacy_api'])
```

#### `disabledCells(array|Closure $map)`


Lock specific cells. Map shape: `rowKey =&gt; [columnKey, ...]`:

```php
->disabledCells([
    'csv_export' => ['low'],
    'dark_mode' => ['high', 'medium'],
])
```

Accepts `Closure` for server-side dynamic maps (re-evaluated on each render; use with `live()` for server-driven updates).

#### `disableCellWhen($row, $column, $whenRow, $whenColumns)`


**Reactive** (client-side Alpine) — disable one cell when a trigger row matches column key(s). No `live()` needed.

```php
// When dark_mode includes High → disable csv_export → High
->disableCellWhen('csv_export', 'high', 'dark_mode', 'high')

// Multiple trigger columns (any match)
->disableCellWhen('csv_export', 'high', 'dark_mode', ['high', 'critical'])
```

| Argument | Description |
|----------|-------------|
| `$row` | Target row to disable |
| `$column` | Target column to disable |
| `$whenRow` | Row to watch |
| `$whenColumns` | `string` or `list&lt;string&gt;` — trigger column key(s) |

| Trigger mode | Match condition |
|--------------|-----------------|
| `radio` | `whenRow` selected column **equals** one of `whenColumns` |
| `checkbox` | `whenRow` selection **includes any** of `whenColumns` |

Invalid selections in newly disabled cells are removed automatically.

#### `disableRowWhen($row, $whenRow, $whenColumns)`


**Reactive** — disable an entire row when trigger row matches:

```php
// When dark_mode is Low → disable entire api_access row
->disableRowWhen('api_access', 'dark_mode', 'low')
```

#### `size('sm'|'md'|'lg')`


Control scale for row labels, column headers, and radio/checkbox indicators. Default: `md`.

```php
->size('sm')  // compact tables
->size('lg')  // touch-friendly
```

#### `color('primary'|'secondary'|'success'|'warning'|'danger'|null)`


Filament accent for selected radio/checkbox indicators. Default: `primary`.

```php
->color('success')
```

### Inherited Filament field API

Also supports standard [Inherited Filament field API](/docs/configuration):

| Method | Typical use |
|--------|-------------|
| `label()` / `helperText()` | Field title above grid |
| `required()` | All rows required (unless `requiredRows()` narrows scope) |
| `disabled()` | Disable entire field |
| `default()` / `dehydrated()` | Initial state and persistence |
| `live()` | Optional — not needed for `disableCellWhen` / `disableRowWhen` |
| `afterStateUpdated()` | React to changes (autosave, logging) |
| `rule()` | Custom validation (see above) |

### Public helper methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getMode()` | `string` | `radio` or `checkbox` |
| `isCheckboxMode()` | `bool` | Checkbox mode flag |
| `getRowKeys()` / `getColumnKeys()` | `list&lt;string&gt;` | Valid keys |
| `getNormalizedRows()` | `array` | Merged row metadata |
| `getNormalizedColumns()` | `array` | Merged column metadata |
| `getDisabledCellsMap()` | `array&lt;string, list&lt;string&gt;&gt;` | Static disabled cells |
| `getConditionalDisableRules()` | `list&lt;array&gt;` | `disableCellWhen` / `disableRowWhen` rules |
| `matchesConditionalDisableRule($rule, $state)` | `bool` | Test rule against state |
| `isRowDisabled($row, $state?)` | `bool` | Static + conditional row lock |
| `isCellDisabled($row, $column, $state?)` | `bool` | Static + conditional cell lock |
| `dehydrateValue($state)` | `array` | Normalize state for storage |
| `getWrapperClasses()` | `list&lt;string&gt;` | `fff-matrix-choice` BEM classes |
| `getMatrixSizeStyles()` | `array` | CSS custom properties |

### FlexField schema config

| Config key | Maps to |
|------------|---------|
| `mode` | `mode()` |
| `rows` | `rows()` |
| `columns` | `matrixColumns()` |
| `column_icons` | `columnIcons()` |
| `disabled_rows` | `disabledRows()` |
| `required_rows` | `requiredRows()` |
| `disabled_cells` | `disabledCells()` |
| `disable_cell_when` | `disableCellWhen()` — list of rule arrays |
| `disable_row_when` | `disableRowWhen()` — list of rule arrays |
| `size` | `size()` — default from `config('filament-flex-fields.ui.matrix_choice_size', 'md')` |
| `color` | `color()` |

`disable_cell_when` / `disable_row_when` rule array:

```php
'disable_cell_when' => [
    [
        'row' => 'csv_export',
        'column' => 'high',
        'when_row' => 'dark_mode',
        'when_columns' => 'high', // or ['high', 'medium']
    ],
],
'disable_row_when' => [
    [
        'row' => 'api_access',
        'when_row' => 'dark_mode',
        'when_columns' => 'low',
    ],
],
```

### CSS classes

| Class | Role |
|-------|------|
| `fff-matrix-choice` | Root wrapper |
| `fff-matrix-choice--{sm\|md\|lg}` | Size modifier |
| `fff-matrix-choice--{radio\|checkbox}` | Mode modifier |
| `fff-matrix-choice__frame` | Gray outer frame |
| `fff-matrix-choice__header` | Column header row |
| `fff-matrix-choice__body` | White inset panel |
| `fff-matrix-choice__row` | Data row |
| `fff-matrix-choice__cell` | Clickable grid cell |
| `fff-matrix-choice__cell.is-selected` | Selected cell (animated indicator) |
| `fff-matrix-choice__cell.is-disabled` | Locked cell |

### Implementation notes

- Radio/checkbox indicators reuse Flex Radiolist / Flex Checklist animation tokens (`fff-choice-cards-indicator-pop`).
- All clicks are handled on `fff-matrix-choice__cell`; inner inputs use `pointer-events-none` to prevent double-toggle.
- Conditional rules update locally when the selection changes. Newly disabled selections are cleared; the server independently rejects submitted selections that violate those rules.
---

### Playground

Slug: **`matrix-choice`**

| Demo field | Shows |
|------------|-------|
| Mood grid | Radio mode, compact size |
| Feature priorities | Checkbox mode, per-row limits, conditional disabling |

`/admin/flex-fields/matrix-choice` — see [Playground](/docs/index#playground).

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [FlexMatrixTable](/docs/flex-matrix-table) | Editable spreadsheet-style matrix |
| [FlexRadiolist](/docs/flexradiolist) | Single column of options, not a grid |
| [FlexChecklist](/docs/flexchecklist) | Vertical checklist without column headers |
| [NpsField](/docs/nps-field) | Single-axis survey scale (NPS / CSAT) |

---

### Keyboard and text direction

Tab enters the grid and the next Tab leaves it. Arrow keys move spatially between
enabled cells; Home and End reach the enabled endpoints of the current row.
Space or Enter selects the focused cell. Holding the key does not toggle it
repeatedly. Horizontal arrows follow RTL direction.

```php
MatrixChoiceField::make('availability')
    ->rows(['monday' => 'Monday', 'tuesday' => 'Tuesday'])
    ->matrixColumns(['morning' => 'Morning', 'afternoon' => 'Afternoon'])
    ->extraAttributes(['dir' => 'rtl']);
```

Radio row values must be string or integer column keys. Checkbox row values must
be lists of unique string or integer column keys. Booleans, nested objects,
associative lists, unknown rows and selections in disabled cells fail server
validation. Per-row maximums also prevent further selection immediately in the UI.
Reactive row, column and cell configuration is reevaluated when the form renders.
