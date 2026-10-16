---
title: "NumberStepper"
description: Pill-shaped numeric control with − / + buttons and an animated NumberFlow display.
icon: "list-plus"
"canonical": "https://flex-fields.bjanczak.com/docs/numberstepper"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/number-stepper.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "NumberStepper in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/number-stepper-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/number-stepper-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/number-stepper.webp"
"twitter:image:alt": "NumberStepper in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/numberstepper#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/numberstepper",
      "name": "NumberStepper",
      "description": "Pill-shaped numeric control with − / + buttons and an animated NumberFlow display.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/numberstepper#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/numberstepper#article",
      "url": "https://flex-fields.bjanczak.com/docs/numberstepper",
      "headline": "NumberStepper",
      "description": "Pill-shaped numeric control with − / + buttons and an animated NumberFlow display.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/numberstepper#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/number-stepper-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/number-stepper-motion.mp4#video",
      "name": "NumberStepper — FLEX Fields demonstration",
      "description": "A recorded demonstration of NumberStepper in the FLEX Fields playground for Filament. Pill-shaped numeric control with − / + buttons and an animated NumberFlow display.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/number-stepper.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.167S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/number-stepper-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/numberstepper#article"
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
  src="/art/previews/number-stepper-motion.mp4"
  poster="/art/previews/number-stepper.webp"
  width="1920"
  height="1080"
  aria-label="NumberStepper — FLEX Fields demonstration"
>
  <a href="/art/previews/number-stepper-motion.mp4">Watch the NumberStepper demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Pill-shaped numeric control with **−** / **+** buttons and an animated **NumberFlow** display. Ideal for quantities, guests, or any numeric input where precise stepping is preferred over direct text entry.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper` |
| **State type** | `int|float|null` when `nullable()` |
| **Model cast** | `'quantity' => 'integer'` or `'decimal:2'` |
| **FieldType** | `number_stepper` |
| **Playground** | `number-stepper` slug in Flex Fields playground |

Works with all standard Filament field APIs: `required()`, `disabled()`, `hidden()`, `live()`, `afterStateUpdated()`, validation rules, etc.

---

### Basic usage

#### Standard Quantity
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;

NumberStepper::make('quantity')
    ->label('Quantity')
    ->minValue(0)
    ->maxValue(99)
    ->step(1)
    ->suffix('kg');
```

#### Currency Input
```php
NumberStepper::make('price')
    ->prefix('$')
    ->decimalPlaces(2)
    ->integer(false)
    ->step(0.5)
    ->minValue(0);
```

---

### State & validation

#### Stored value
State is a numeric value (`int` or `float`) or `null` if nullable.

```php
$record->quantity; // int|null — e.g. 5
```

#### Validation rules (built-in)
| Rule | When |
|------|------|
| `integer` | When `->integer()` (default: true) |
| Lower bound | When `->minValue($n)`, including nonempty values in `nullable()` mode |
| `max:$n` | When `->maxValue($n)` |
| Numeric shape | Rejects structured values, booleans, nonnumeric text, nonfinite numbers and values outside the browser-safe numeric range |
| `required` | When `->required()` |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `minValue(int\|float\|numeric-string\|null $value)` | Setup | `null` | Lower bound; a closure can resolve it from the form |
| `maxValue(int\|float\|numeric-string\|null $value)` | Setup | `null` | Upper bound; a closure can resolve it from the form |
| `step(int\|float $step)` | Setup | `1` | Increment / decrement step |
| `integer(bool $condition)` | Setup | `true` | Restrict to whole numbers |
| `nullable(bool $condition)` | Setup | `false` | Allows `null` state |
| `variant(string $variant)` | Setup | `'default'` | `default`, `primary`, `secondary`, `tertiary`, `outline` |
| `prefix(string $prefix)` | Setup | `null` | Static text before the value |
| `suffix(string $suffix)` | Setup | `null` | Static text after the value |
| `suffixForms(array $forms)` | Setup | `[]` | Animated unit chosen using the locale's plural categories; requires `other` |
| `nullLabel(string $label)` | Setup | `'—'` | Text shown when value is `null` |
| `decrementIcon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | Setup | config | Custom minus icon; `null` restores the shared default |
| `incrementIcon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | Setup | config | Custom plus icon; `null` restores the shared default |
| `icons(array $icons)` | Setup | — | Shorthand for `decrement` and `increment` keys |
| `reversed(bool $condition)` | Setup | `false` | Swaps button positions |
| `decimalPlaces(int $places)` | Setup | `null` | Display precision, from 0 to 20; does not round the saved decimal value |
| `locale(string\|Closure\|null $locale)` | Setup | `null` | Locale for the displayed number (`pt_BR` → `1.234,50`). `null` keeps the plain unformatted output |
| `wheelAnimated(bool $condition)` | Setup | `true` | Animate digits, changing units and boundary feedback; respects reduced motion |
| `size(string $size)` | Setup | `'md'` | `sm`, `md`, `lg` |

---

### Real-world examples

#### Long unit labels

```php
NumberStepper::make('reservations')
    ->label('Reservations')
    ->default(1)
    ->minValue(1)
    ->maxValue(150)
    ->size('lg')
    ->locale('en')
    ->suffixForms([
        'one' => 'reservation',
        'other' => 'reservations',
    ]);
```

The control sizes itself to the number and unit. Long labels are supported in all three sizes, including the change from two to three digits. Number and unit are vertically centered in both the initial server-rendered view and the animated view. The playground includes `reservation/reservations` in `sm`, `md` and `lg` for comparison.

#### Animated singular and plural units

```php
NumberStepper::make('seats')
    ->label('Seats')
    ->minValue(1)
    ->maxValue(5)
    ->default(1)
    ->locale('en')
    ->suffixForms([
        'one' => 'seat',
        'other' => 'seats',
    ]);
```

The unit changes locally with the number; it does not need a server request for each click. Unchanged letters stay in place while added or removed characters animate. A static `suffix('items')` retains the same text for every value. When forms are configured, they take precedence over the static suffix.

Use the plural categories appropriate for your language:

```php
NumberStepper::make('seats')
    ->locale('pl')
    ->suffixForms([
        'one' => 'miejsce',
        'few' => 'miejsca',
        'many' => 'miejsc',
        'other' => 'miejsca',
    ])
    ->minValue(0)
    ->maxValue(100);
```

Supported categories are `zero`, `one`, `two`, `few`, `many` and `other`. Omitted categories use `other`. PHP uses its internationalization extension for localized plural selection; the browser uses its built-in plural rules. Configure the same locale for both. Without the PHP internationalization extension, server rendering uses a singular/plural fallback.

#### Boundary feedback and nullable values

At a configured minimum or maximum, another press gives a short spring response: downward at the minimum and upward at the maximum. Repeated presses increase the response within a small cap. The value remains unchanged and no update is submitted for a refused step. A disabled field does not respond.

```php
NumberStepper::make('limit')
    ->nullable()
    ->nullLabel('No limit')
    ->minValue(0)
    ->maxValue(10);
```

In nullable mode, decrementing at the minimum selects `null`; incrementing from `null` selects the minimum (or the step when no minimum is configured). This is a valid state change, so it does not produce the minimum-boundary response.

Users who prefer reduced motion receive immediate changes. Use `wheelAnimated(false)` to disable the component's animations explicitly.

#### Debounced server updates

```php
NumberStepper::make('quantity')
    ->minValue(1)
    ->maxValue(100)
    ->live(debounce: 250);
```

The displayed number changes immediately. Rapid clicks are grouped into one server update after the configured pause. Without `live()`, the form still submits the current value normally. Use validated form state when saving; client-side bounds do not replace server validation.

#### Keyboard control

Focus the value and use Arrow Up / Arrow Down to step, Home to select the configured minimum, and End to select the configured maximum. Buttons also work with Enter and Space. Direct text entry remains disabled; use FlexTextInput when users need to type numbers.

#### Custom icons and reversed controls

```php
NumberStepper::make('zoom')
    ->minValue(1)
    ->maxValue(10)
    ->icons([
        'decrement' => 'hugeicons-search-minus',
        'increment' => 'hugeicons-search-add',
    ])
    ->reversed()
    ->variant('secondary')
    ->size('sm');
```

`reversed()` swaps the buttons without changing their meaning or keyboard directions. Icon configuration accepts icon names, backed enums or rendered icon HTML. Individual `decrementIcon()` and `incrementIcon()` settings can be used instead of `icons()`.

```php
NumberStepper::make('zoom')
    ->minValue(1)
    ->maxValue(10)
    ->decrementIcon('hugeicons-search-minus')
    ->incrementIcon('hugeicons-search-add');
```

#### Sizes and surfaces

```php
NumberStepper::make('quantity')
    ->minValue(0)
    ->maxValue(99)
    ->size('md')
    ->variant('outline')
    ->wheelAnimated(false);
```

Choose `sm`, `md` or `lg` and `default`, `primary`, `secondary`, `tertiary` or `outline`. `disabled()` disables the whole control; the original sizes and surfaces work in light and dark mode.

#### Guest Selection (Wizard)
```php
NumberStepper::make('guests')
    ->label('Guests')
    ->helperText('Maximum 10 guests per reservation')
    ->minValue(1)
    ->maxValue(10)
    ->variant('primary')
    ->size('lg');
```

#### Reactive Price Calculator
```php
NumberStepper::make('quantity')
    ->minValue(1)
    ->live()
    ->afterStateUpdated(fn (Set $set, $state) => $set('total', $state * 10.5));
```

---

### Playground

`/admin/flex-fields/number-stepper`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [FlexSlider](/docs/flexslider) | Visual range selection with a track |
| [TrackSlider](/docs/trackslider) | Compact track-based slider |
| [FlexTextInput](/docs/flextextinput) | Standard text entry for large numbers |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-number-stepper` | Root wrapper |
| `fff-number-stepper--{sm\|md\|lg}` | Size variant |
| `fff-number-stepper--{variant}` | Visual variant |
| `fff-number-stepper__value` | Value container |
| `fff-number-stepper__value-sizer` | Hidden sizer for width stability |
| `fff-number-stepper__value-display` | Visible value wrapper |
| `fff-number-stepper__ssr-value` | Server-rendered initial value |
| `fff-number-stepper__null-label` | Label for null state |
| `fff-number-stepper__flow` | NumberFlow element when no unit is configured |
| `fff-number-stepper__dynamic-flow` | NumberFlow element beside a configured unit |
| `fff-number-stepper__live-value` | Centered number and unit row |
| `fff-number-stepper__dynamic-suffix` | Static or pluralized unit |
| `is-disabled` | Disabled state |
| `is-reversed` | Reversed layout |
