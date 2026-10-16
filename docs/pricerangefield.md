---
title: "PriceRangeField"
description: "Choose a price or budget range with two handles, an animated histogram and shared FLEX text inputs."
icon: "badge-dollar-sign"
"canonical": "https://flex-fields.bjanczak.com/docs/pricerangefield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/price-range.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "PriceRangeField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/price-range-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/price-range-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/price-range.webp"
"twitter:image:alt": "PriceRangeField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/pricerangefield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/pricerangefield",
      "name": "PriceRangeField",
      "description": "Choose a price or budget range with two handles, an animated histogram and shared FLEX text inputs.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/pricerangefield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/pricerangefield#article",
      "url": "https://flex-fields.bjanczak.com/docs/pricerangefield",
      "headline": "PriceRangeField",
      "description": "Choose a price or budget range with two handles, an animated histogram and shared FLEX text inputs.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/pricerangefield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/price-range-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/price-range-motion.mp4#video",
      "name": "PriceRangeField — FLEX Fields demonstration",
      "description": "A recorded demonstration of PriceRangeField in the FLEX Fields playground for Filament. Choose a price or budget range with two handles, an animated histogram and shared FLEX text inputs.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/price-range.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT7.783S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/price-range-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/pricerangefield#article"
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
  src="/art/previews/price-range-motion.mp4"
  poster="/art/previews/price-range.webp"
  width="1920"
  height="1080"
  aria-label="PriceRangeField — FLEX Fields demonstration"
>
  <a href="/art/previews/price-range-motion.mp4">Watch the PriceRangeField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Select a minimum and maximum price by dragging two handles or entering exact amounts. Use the histogram to show a price distribution, compare budgets or build catalog filters.

<CardGroup cols={2}>
  <Card title="Precise amounts" icon="coins">Integer or decimal amounts, currency prefixes and a configurable step.</Card>
  <Card title="Shared input design" icon="rectangle-list">The same input presentation, sizes, rounding and appearance variants as Flex Text Input.</Card>
</CardGroup>

## Quick example

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PriceRangeField;

PriceRangeField::make('budget')
    ->label('Budget')
    ->min(0)
    ->max(5000)
    ->step(10)
    ->prefix('$')
    ->default(['min' => 100, 'max' => 2000]);
```

The value is `['min' => 100, 'max' => 2000]`. Without an explicit default, a new field starts with its full configured range. An optional field may also store `null`.

## Bounds, steps and decimal amounts

`min()` and `max()` must be finite numbers, with the maximum greater than the minimum. `step()` must be positive. Steps start at the configured minimum; for `min(5)->step(10)`, valid values include `5`, `15` and `25`. The maximum endpoint remains selectable even when the last step is shorter.

Integer mode is enabled by default and requires integer bounds and steps. Disable it for cents or other fractions:

```php
PriceRangeField::make('hourly_budget')
    ->min(1.25)
    ->max(250.25)
    ->step(0.25)
    ->integer(false)
    ->decimalPlaces(2)
    ->prefix('€')
    ->default(['min' => 25.50, 'max' => 100.75]);
```

`decimalPlaces()` accepts `0`–`10` or `null`. Your precision must represent the configured bounds and step. Without a fixed precision, snapping supports up to ten decimal places. Numeric values must remain within JavaScript's safe numeric range; do not use this field for arbitrary-precision monetary calculations.

`locale()` formats the accessible value announcement. The editable native number inputs retain their browser's numeric editing behavior; the stored value always contains numbers, without currency symbols or grouping separators.

```php
PriceRangeField::make('budget')
    ->min(0)
    ->max(5000)
    ->prefix('€')
    ->locale('de-DE');
```

## Input appearance

<Tabs>
<Tab title="Variants">

```php
PriceRangeField::make('standard')->variant('primary');
PriceRangeField::make('subtle')->variant('secondary');
PriceRangeField::make('minimal')->variant('flat');
```

`primary` uses the standard bordered input, `secondary` uses the shared secondary background, and `flat` removes the input chrome. Legacy `bordered` and `faded` names map to `primary` and `secondary`.

</Tab>
<Tab title="Sizes and rounding">

```php
PriceRangeField::make('compact')->size('sm');
PriceRangeField::make('regular')->size('md');
PriceRangeField::make('large')->size('lg');

PriceRangeField::make('rounded')
    ->rounding('full')
    ->focusOutline();
```

Sizes accept `sm`, `md`, `lg` or the corresponding `ControlSize` enum. `rounding()` accepts `native`, `md`, `lg` and `full`. The shared whole-input focus outline is enabled by default; use `focusOutline(false)` to disable it. Keyboard focus remains visible on slider handles.

</Tab>
<Tab title="Labels and prefixes">

```php
PriceRangeField::make('monthly_budget')
    ->prefix('$')
    ->minInputLabel('Minimum monthly budget')
    ->maxInputLabel('Maximum monthly budget');

PriceRangeField::make('plain_budget')->withoutPrefix();
```

The default prefix is `null`. Input labels default to translated “Min price” and “Max price” and also label the slider handles for assistive technology.

</Tab>
</Tabs>

## Histogram

Supply a list of heights. Heights are clamped to `8`–`100`, with at most `1024` source bars. Invalid shapes and nonfinite heights are rejected. CSS container queries choose the displayed density from the available width, in groups of eight bars and up to 128 visible bars. Each density resamples the whole source distribution. This works before JavaScript starts and on resize, so activation does not change bar thickness.

```php
PriceRangeField::make('budget')
    ->min(0)
    ->max(3000)
    ->histogram([12, 24, 38, 66, 90, 100, 85, 62, 40, 18]);
```

An empty list selects the default 32-height histogram. Each position represents an equal interval across the configured range; pass heights derived from your price buckets, rather than raw product prices.

```php
PriceRangeField::make('budget')
    ->histogram(fn (): array => $priceBucketHeights);
```

## Slider without amount inputs

```php
PriceRangeField::make('budget')
    ->prefix('€')
    ->showInputs(false);
```

Handles retain keyboard controls and currency-aware announcements. The input presentation stylesheet is requested only by instances that render amount inputs, and is shared with other FLEX inputs on the page. The standalone Flex Text Input JavaScript factory is not required by Price Range.

## Editing and events

- Dragging previews the range locally and commits one complete `min`/`max` pair on release.
- Handles cannot pass one another. Typing a minimum above the maximum raises the maximum; typing a maximum below the minimum lowers the minimum.
- Amounts commit on change, blur or Enter. This lets users finish typing before snapping and updating the range.
- Escape, pointer cancellation, loss of pointer capture or window focus cancels an unfinished drag.
- Arrow keys change one step; Shift multiplies it by ten. Page Up/Down move ten steps. Home/End move toward the available endpoints, respecting the other handle.
- Horizontal arrow keys and handle positions follow RTL direction.
- Reduced-motion preferences disable local transitions.

For server-side reactions, use Filament's state binding and update hooks:

```php
PriceRangeField::make('budget')
    ->live()
    ->afterStateUpdated(function (?array $state): void {
        // Update dependent filters after a complete range is committed.
    });
```

The root emits bubbling `input` events during drag previews and `change` when a different pair is committed. Both contain `{ min, max }` in `event.detail`. A preview event does not mean the persisted form state has been committed.

```php
PriceRangeField::make('budget')
    ->extraAttributes([
        'x-on:change' => 'console.log($event.detail.min, $event.detail.max)',
    ]);
```

## Server validation and storage

Submission checks the exact `min`/`max` object shape, finite numeric endpoints, configured bounds, integer/decimal policy, step alignment and `min <= max`. Additional keys, missing endpoints, booleans, nested objects and invalid numeric strings are rejected. Valid numeric strings become numbers. Invalid submitted values are not silently clamped into valid ones.

```php
PriceRangeField::make('budget')
    ->required()
    ->min(0)
    ->max(10000)
    ->step(50);
```

`readOnly()` prevents editing but still validates submitted values. For a calculated display that must not be saved, use `dehydrated(false)`. Disabled fields follow Filament's normal disabled/dehydration policy.

```php
PriceRangeField::make('calculated_budget')
    ->readOnly()
    ->dehydrated(false);
```

Store the pair in a JSON column with an Eloquent array cast:

```php
protected function casts(): array
{
    return ['budget' => 'array'];
}
```

When saving a custom form, read its validated state rather than writing an unvalidated public Livewire property:

```php
$data = $this->form->getState();
$record->update($data);
```

## Configuration reference

Configuration methods support closures with Filament utility injection unless noted.

| Method | Default | Purpose |
| --- | --- | --- |
| `min(int\|float\|Closure)` | `0` | Lower bound. |
| `max(int\|float\|Closure)` | `1000` | Upper bound. |
| `step(int\|float\|Closure)` | `1` | Increment relative to the lower bound. |
| `integer(bool\|Closure)` | `true` | Require whole-number bounds, step and values. |
| `decimalPlaces(int\|Closure\|null)` | `null` | Decimal precision, `0`–`10`. |
| `locale(string\|Closure\|null)` | `null` | Accessible numeric value formatting. |
| `prefix(string\|Closure\|null)` | `null` | Currency or unit prefix. |
| `withoutPrefix()` | — | Clear the prefix; takes no arguments. |
| `variant(string\|Closure)` | `primary` | `primary`, `secondary` or `flat`. |
| `size(string\|ControlSize\|Closure)` | `md` | `sm`, `md` or `lg`. |
| `rounding(string\|Closure)` | `md` | `native`, `md`, `lg` or `full`. |
| `focusOutline(bool\|Closure)` | `true` | Whole-input focus outline. |
| `showInputs(bool\|Closure)` | `true` | Show editable amounts. |
| `minInputLabel(string\|Closure\|null)` | Translated minimum label | Minimum input and handle label. |
| `maxInputLabel(string\|Closure\|null)` | Translated maximum label | Maximum input and handle label. |
| `histogram(array\|Closure)` | Default 32 heights | Distribution heights; up to 1024 source bars. |
| `readOnly(bool\|Closure)` | `false` | Prevent editing. |

Standard Filament methods such as `label()`, `default()`, `required()`, `disabled()`, `rules()`, `live()`, `helperText()` and `columnSpanFull()` remain available.

## FLEX schema configuration

The schema field type is `price_range`. Its supported component keys are `min`, `max`, `step`, `size`, `variant`, `rounding`, `prefix`, `histogram`, `integer`, `decimal_places`, `show_inputs`, `min_input_label`, `max_input_label`, `locale`, `focus_outline` and `read_only`, together with shared schema settings. `read_only` maps to `readOnly()` for schema-built fields.

## Related components

<CardGroup cols={3}>
  <Card title="FlexSlider" icon="sliders" href="/docs/flexslider">A general slider with optional range selection.</Card>
  <Card title="TrackSlider" icon="grip-lines" href="/docs/trackslider">A compact single-value control.</Card>
  <Card title="CurrencyField" icon="coins" href="/docs/currencyfield">An individual amount input.</Card>
</CardGroup>
