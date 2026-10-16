---
title: "TrafficSplit"
description: "Allocate A/B testing traffic and other percentages with a validated 100% split, custom labels, locked segments and dynamic repeater integration."
icon: "split"
"canonical": "https://flex-fields.bjanczak.com/docs/trafficsplit"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/traffic-split.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "TrafficSplit in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/traffic-split-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/traffic-split-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/traffic-split.webp"
"twitter:image:alt": "TrafficSplit in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/trafficsplit#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/trafficsplit",
      "name": "TrafficSplit",
      "description": "Allocate A/B testing traffic and other percentages with a validated 100% split, custom labels, locked segments and dynamic repeater integration.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/trafficsplit#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/trafficsplit#article",
      "url": "https://flex-fields.bjanczak.com/docs/trafficsplit",
      "headline": "TrafficSplit",
      "description": "Allocate A/B testing traffic and other percentages with a validated 100% split, custom labels, locked segments and dynamic repeater integration.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/trafficsplit#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/traffic-split-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/traffic-split-motion.mp4#video",
      "name": "TrafficSplit — FLEX Fields demonstration",
      "description": "A recorded demonstration of TrafficSplit in the FLEX Fields playground for Filament. Allocate A/B testing traffic and other percentages with a validated 100% split, custom labels, locked segments and dynamic repeater integration.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/traffic-split.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT7.867S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/traffic-split-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/trafficsplit#article"
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
  src="/art/previews/traffic-split-motion.mp4"
  poster="/art/previews/traffic-split.webp"
  width="1920"
  height="1080"
  aria-label="TrafficSplit — FLEX Fields demonstration"
>
  <a href="/art/previews/traffic-split-motion.mp4">Watch the TrafficSplit demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Divide **100% between two to five options**. Drag a divider to change its two neighboring weights together, or use the keyboard. Use TrafficSplit for A/B tests, campaign allocation, routing weights or a percentage-based budget.

<CardGroup cols={2}>
  <Card title="One complete allocation" icon="chart-pie">
    Neighboring weights change together. The total remains 100%.
  </Card>
  <Card title="Dynamic variants" icon="list">
    Link segments to repeater rows and keep their count in sync.
  </Card>
</CardGroup>

## First allocation

Add the field to your form schema:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TrafficSplit;

TrafficSplit::make('weights')
    ->label('Traffic allocation')
    ->segmentCount(3)
    ->labels(['A', 'B', 'Control']);
```

The initial value is `[34, 33, 33]`. The default minimum is **12% per segment**. Labels follow the array order; without custom labels, the field shows `1`, `2`, `3`, and so on.

## Segment count and initial weights

<Tabs>
<Tab title="Two options">

```php
TrafficSplit::make('weights')
    ->segmentCount(2)
    ->labels(['A', 'B'])
    ->default([70, 30]);
```

</Tab>
<Tab title="Four options">

```php
TrafficSplit::make('weights')
    ->segmentCount(4)
    ->labels(['A', 'B', 'C', 'D'])
    ->default([40, 20, 20, 20]);
```

</Tab>
<Tab title="Five options">

```php
TrafficSplit::make('weights')
    ->segmentCount(5)
    ->default([20, 20, 20, 20, 20]);
```

</Tab>
</Tabs>

`segmentCount()` supports 2–5 segments. Direct PHP values outside this range are clamped to the nearest supported count. Supply exactly that many integer weights, each at least `minWeight()`, with a total of 100. `default()` applies to a new form; editing an existing record uses its saved allocation.

Without `default()`, the field distributes weights equally. Any remaining percentage points go to the first segments: three options start at `[34, 33, 33]`, and four at `[25, 25, 25, 25]`.

## Appearance

### Variants

<CodeGroup>

```php Default
TrafficSplit::make('weights')
    ->variant('default');
```

```php Secondary
TrafficSplit::make('weights')
    ->variant('secondary');
```

</CodeGroup>

`default` uses bordered segments. `secondary` uses a filled surface. Both support the panel's light and dark modes. These are the two supported variant names.

### Three sizes

```php
TrafficSplit::make('compact')->size('sm');
TrafficSplit::make('standard')->size('md');
TrafficSplit::make('large')->size('lg');
```

The default size is `md`. You can also pass `ControlSize::Sm`, `ControlSize::Md` or `ControlSize::Lg` from `Bjanczak\FilamentFlexFields\Enums\ControlSize`.

### Custom labels

```php
TrafficSplit::make('routing')
    ->labels(['Europe', 'Americas', 'Asia']);
```

Missing or empty labels fall back to the segment number. Long labels are shortened to fit, with the full label available on hover. Numbers retain space within compact segments.

Restore numbered labels with `labels(null)`:

```php
TrafficSplit::make('weights')
    ->labels(null);
```

### Percentage visibility

```php
TrafficSplit::make('weights')
    ->minWeight(5)
    ->valueThreshold(20);
```

The percentage is displayed at **20% or above** and hidden below it; the segment label remains. The default threshold is 18. It affects presentation only, not the saved value. The effective threshold is at least `minWeight() + 1` and at most 100.

On very narrow segments, the percentage also hides when there is insufficient room, so the segment number is not squeezed. The divider's accessible description still includes both neighboring labels and weights.

## Minimum weights and locked segments

### Set the minimum

```php
TrafficSplit::make('experiment')
    ->segmentCount(3)
    ->minWeight(5)
    ->default([80, 10, 10]);
```

`minWeight(5)` prevents any weight from dropping below 5%. Whole percentage points are used throughout. Zero-weight segments are not supported; the lowest effective minimum is 1%.

<Warning>
  Choose a feasible minimum: `minWeight × segmentCount` must not exceed 100. For five segments, the maximum feasible minimum is 20%. An impossible configuration raises an error.
</Warning>

### Lock a weight in the interface

```php
TrafficSplit::make('weights')
    ->segmentCount(4)
    ->labels(['A', 'B', 'C', 'D'])
    ->default([25, 25, 25, 25])
    ->lockedSegments([1]);
```

Indices start at zero: `[1]` locks the second segment. Both dividers touching it are disabled. In this example, C and D can still exchange weight. A divider cannot move if either neighboring segment is locked. Duplicate indices are ignored, and indices outside the segment count are discarded.

Clear the locks with `lockedSegments([])`.

<Note>
  Locks control interaction. If a percentage must be fixed by your business rules, validate that requirement on the server too; a browser restriction is not an authorization rule.
</Note>

### Enforce a fixed percentage on the server

```php
use Closure;

TrafficSplit::make('weights')
    ->segmentCount(4)
    ->default([25, 25, 25, 25])
    ->lockedSegments([1])
    ->required()
    ->rules([
        fn (): Closure => function (
            string $attribute,
            mixed $value,
            Closure $fail,
        ): void {
            if (! is_array($value) || ($value[1] ?? null) !== 25) {
                $fail('The control group must receive exactly 25%.');
            }
        },
    ]);
```

### Disable the field

```php
TrafficSplit::make('weights')
    ->disabled();
```

Disabled fields ignore pointer and keyboard changes. Filament does not normally save a disabled field's state. Use `->dehydrated()` only when you intentionally want that state included in the submitted data; server-side validation still applies.

## Link to repeater rows

Use a repeater when the user can add and remove variants. Create the allocation once and attach its synchronization callback to the repeater:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Filament\Forms\Components\Repeater;

$allocation = TrafficSplit::make('allocation')
    ->label('Traffic allocation')
    ->linkedToRepeater('variants');

// Place both fields in the same form schema.
[
    Repeater::make('variants')
        ->label('Testing URLs')
        ->maxItems(TrafficSplit::MAX_LINKED_REPEATER_ITEMS)
        ->partiallyRenderAfterActionsCalled()
        ->partiallyRenderComponentsAfterStateUpdated(['allocation'])
        ->afterStateUpdated($allocation->repeaterSyncCallback())
        ->simple(
            FlexTextInput::make('url')
                ->url()
                ->required(),
        )
        ->defaultItems(2)
        ->reorderable(false),
    $allocation,
];
```

- Adding or removing a row distributes the weights equally across the new count.
- Editing a row's URL preserves the current allocation.
- By default, the allocation is hidden below two rows and uses numbered labels.
- Keep the repeater at a maximum of five rows. Linked allocations outside 2–5 rows fail validation when submitted.
- Segment weights follow row order. Disable reordering, as above, when weights must retain their association with individual rows. If you enable reordering, manage the allocation-to-row association in your application.

### Custom labels and visibility threshold

```php
$allocation = TrafficSplit::make('allocation')
    ->labels(['A', 'B', 'C', 'D', 'E'])
    ->linkedToRepeater(
        'variants',
        numericLabels: false,
        minimumItems: 3,
    );
```

`numericLabels: false` retains your custom labels. `minimumItems: 3` shows the field only when three or more rows exist; supported visibility thresholds are 2–5. This is a visibility setting, not a repeater minimum rule. Add `minItems(3)` to the repeater if three rows are mandatory.

The state path is relative to the allocation's container. If both fields are nested in a repeater row or group, use the path to the repeater from that same context. For a linked allocation, the row count determines the segment count, replacing a separately configured `segmentCount()`.

## Dynamic configuration

The component-specific count, minimum, threshold, variant, labels and locks accept closures. Size accepts a string, `ControlSize` or a closure. Closure arguments use Filament's utility injection.

```php
use Filament\Schemas\Components\Utilities\Get;

TrafficSplit::make('weights')
    ->segmentCount(fn (Get $get): int => (int) ($get('option_count') ?: 3))
    ->minWeight(fn (Get $get): int => $get('strict_minimum') ? 15 : 5)
    ->labels(fn (Get $get): array => $get('allocation_labels') ?: [])
    ->variant(fn (Get $get): string => $get('filled_style')
        ? 'secondary'
        : 'default');
```

Make source fields `live()` so configuration changes reach the server and rerender the allocation. Changing the segment count recovers an allocation for the new count. Keep the minimum feasible for every possible count.

`linkedToRepeater()` accepts a string or closure for its path; `numericLabels` and `minimumItems` are fixed arguments.

## Validation and database storage

### Built-in validation

Validation runs when the form is submitted. A valid allocation is an ordered list with:

- exactly the configured number of weights;
- integer percentages between `minWeight()` and 100;
- a total of exactly 100;
- a supported linked repeater count, when configured.

Decimals, negative values, booleans, nested arrays, keyed objects, malformed numeric strings and incorrect totals are rejected. Integer strings such as `'25'` are converted to integers.

```php
TrafficSplit::make('weights')
    ->segmentCount(3)
    ->minWeight(10)
    ->default([50, 25, 25])
    ->required();
```

Use `required()` when an allocation is mandatory. Standard Filament rules, labels, helper text and state-binding options are available. Add business validation with `rules()` as shown above.

### Loading existing values

Initial hydration can recover an invalid saved allocation to an equal split or redistribute available weight across unlocked segments. That recovery is separate from submission validation: a forged payload is rejected rather than silently repaired during save.

### Save to a JSON column

The field stores a list such as `[50, 25, 25]`. Use a JSON column and an Eloquent array cast:

```php
// In your migration:
$table->json('weights')->nullable();
```

```php
// In your model:
protected function casts(): array
{
    return [
        'weights' => 'array',
    ];
}
```

Use the same name for the field and model attribute:

```php
TrafficSplit::make('weights')
    ->segmentCount(3)
    ->required();
```

A normal Filament resource saves the validated state through its form workflow. In a custom Livewire form, obtain the validated data with `$this->form->getState()` before saving it. Do not persist unvalidated public properties directly.

## Keyboard, touch and change events

| Action | Behavior |
|---|---|
| Tab | Focus the next unlocked divider |
| Left / Right | Move by one percentage point; direction follows RTL layouts |
| Up / Down | Increase / decrease by one percentage point |
| Shift + arrow | Move by ten percentage points |
| Home / End | Move to the allowed minimum / maximum boundary |
| Escape during drag | Restore the allocation from before the drag |
| Cancelled pointer gesture | Restore the allocation from before the drag |

Touch and pen use the same dividers as the mouse. Track measurements adjust to scroll and resize during a gesture. Locked and disabled dividers are skipped by keyboard focus.

The view updates while dragging, and one complete allocation is committed when the gesture ends. Keyboard changes commit immediately. Deferred binding waits for the form's next request; `live()` requests a server update after a committed change, rather than after every pointer movement.

```php
TrafficSplit::make('weights')
    ->live()
    ->afterStateUpdated(function (?array $state): void {
        // React to the complete allocation, for example [60, 40].
    });
```

The component also emits bubbling browser `input` and `change` events with `event.detail.weights` after a committed change:

```javascript
document.addEventListener('change', (event) => {
    if (!event.target?.closest?.('.fff-traffic-split')) return;

    const weights = event.detail?.weights;
    if (Array.isArray(weights)) {
        console.log(weights);
    }
});
```

For a linked repeater, `linkedToRepeater()` defaults to deferred allocation binding. Call `live()` after it if you explicitly need a server callback on each completed allocation change.

## Configuration reference

| Option | Default | Accepted values / behavior |
|---|---|---|
| `segmentCount($count)` | `3` | Integer or closure; effective count 2–5 |
| `minWeight($weight)` | `12` | Integer or closure; effective minimum 1–100, with `minimum × count ≤ 100` |
| `valueThreshold($threshold)` | `18` | Integer or closure; hides the percentage below the effective threshold |
| `variant($variant)` | `'default'` | `'default'`, `'secondary'`, or closure |
| `labels($labels)` | `null` | Ordered array of strings, closure or `null` |
| `lockedSegments($indices)` | `[]` | Zero-based indices or closure; disables touching dividers |
| `size($size)` | `'md'` | `'sm'`, `'md'`, `'lg'`, `ControlSize` or closure |
| `linkedToRepeater($path, $numericLabels, $minimumItems)` | Unlinked | Relative path or closure; numbered labels default to `true`, visibility minimum to `2` |
| `repeaterSyncCallback()` | — | Callback for the linked repeater's `afterStateUpdated()` |

Inherited form options include `default()`, `required()`, `rules()`, `disabled()`, `label()`, `helperText()`, `columnSpanFull()`, `live()` and `afterStateUpdated()`. These follow the normal Filament form API.

## FLEX schema configuration

Use `traffic_split` as the schema type. `traffic-split` is the catalog route and asset identifier, not the saved schema type.

```php
[
    'type' => 'traffic_split',
    'slug' => 'weights',
    'label' => 'Traffic allocation',
    'is_required' => true,
    'default_value' => [50, 25, 25],
    'config' => [
        'segment_count' => 3,
        'min_weight' => 10,
        'value_threshold' => 18,
        'size' => 'md',
        'variant' => 'secondary',
        'labels' => ['A', 'B', 'Control'],
        'locked_segments' => [],
        'disabled' => false,
        'linked_repeater' => null,
        'linked_repeater_numeric_labels' => true,
        'linked_repeater_minimum_items' => 2,
    ],
];
```

All eleven `config` keys map to the PHP options above. `linked_repeater` is a relative repeater path; leave it `null` for a standalone field. Set up the repeater callback and partial rendering in the containing form, as in the repeater example. Labels, defaults and required status are field-level properties outside `config`.

## Related components

<CardGroup cols={3}>
  <Card title="FlexSlider" icon="sliders" href="/docs/flexslider">
    One value or a pair of bounds on a numeric scale.
  </Card>
  <Card title="TrackSlider" icon="audio-lines" href="/docs/trackslider">
    A single numeric value on a compact track.
  </Card>
  <Card title="ProgressBar" icon="bars-progress" href="/docs/progressbar">
    Display a percentage without editing it.
  </Card>
</CardGroup>
