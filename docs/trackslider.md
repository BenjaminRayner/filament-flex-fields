---
title: "TrackSlider"
description: Minimalist track-based range slider with optional live value output.
icon: "audio-lines"
"canonical": "https://flex-fields.bjanczak.com/docs/trackslider"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/track-slider.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "TrackSlider in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/track-slider-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/track-slider-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/track-slider.webp"
"twitter:image:alt": "TrackSlider in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/trackslider#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/trackslider",
      "name": "TrackSlider",
      "description": "Minimalist track-based range slider with optional live value output.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/trackslider#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/trackslider#article",
      "url": "https://flex-fields.bjanczak.com/docs/trackslider",
      "headline": "TrackSlider",
      "description": "Minimalist track-based range slider with optional live value output.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/trackslider#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/track-slider-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/track-slider-motion.mp4#video",
      "name": "TrackSlider — FLEX Fields demonstration",
      "description": "A recorded demonstration of TrackSlider in the FLEX Fields playground for Filament. Minimalist track-based range slider with optional live value output.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/track-slider.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.083S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/track-slider-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/trackslider#article"
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
  src="/art/previews/track-slider-motion.mp4"
  poster="/art/previews/track-slider.webp"
  width="1920"
  height="1080"
  aria-label="TrackSlider — FLEX Fields demonstration"
>
  <a href="/art/previews/track-slider-motion.mp4">Watch the TrackSlider demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Minimalist track-based range slider where the entire bar acts as the interactive surface. Features a clean, handle-less design with optional live value output and track labels. Often used for percentage inputs or subtle settings.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\TrackSlider` |
| **State type** | `int\|float` |
| **Model cast** | `'volume' => 'integer'` or `'float'` |
| **FieldType** | `range_slider` (optional `suffix` / `show_output` in config; Flex Forms studio label: Slider) |
| **Playground** | `track-slider` slug in Flex Fields playground |

---

### Basic usage

#### Standard Percentage
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TrackSlider;

TrackSlider::make('progress')
    ->label('Progress')
    ->min(0)
    ->max(100)
    ->suffix('%');
```

#### Settings Control
```php
TrackSlider::make('font_size')
    ->trackLabel('Font Size')
    ->min(0.5)
    ->max(2)
    ->step(0.1)
    ->integer(false)
    ->decimalPlaces(1);
```

---

### State & validation

#### Stored value
State is a numeric value (`int` or `float`).

```php
$record->progress; // 75
```

#### Validation rules
The field validates the numeric type and the configured `min()` and `max()` on the server. With `integer()` enabled, fractional submissions fail validation. Malformed values, arrays, booleans and nonfinite numbers are rejected rather than silently converted into valid values. Add application rules with `rules()` and use `required()` when a value must be supplied.

```php
TrackSlider::make('capacity')
    ->min(1)
    ->max(10)
    ->required();
```

`step()` controls pointer and keyboard increments; it does not add a server-side multiple-of-step rule. Add such a rule if your application requires it. Bounds must be finite and ordered, and the step must be positive. Equal bounds create a fixed-value field.

### Interaction

Click or drag anywhere on the track. Arrow keys change the value by one step; `Home` and `End` select the exact bounds. The marker stays slightly inside both edges, while the fill still represents the full 0–100% range. Disabled fields ignore pointer and keyboard changes.

The three sizes share a subtle gray track and darker fill without a shadow. `secondary` provides a stronger fill contrast. Both variants support dark mode and reduced motion.

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `min(int\|float $min)` | Setup | `0` | Lower bound |
| `max(int\|float $max)` | Setup | `100` | Upper bound |
| `step(int\|float $step)` | Setup | `1` | Increment step |
| `integer(bool $condition)` | Setup | `true` | Snap to whole numbers |
| `showOutput(bool $condition)` | Setup | `true` | Display current value inside track |
| `suffix(string $suffix)` | Setup | `null` | Value suffix (e.g. `%`) |
| `variant(string $variant)` | Setup | `'default'` | `default`, `secondary` |
| `decimalPlaces(int $places)` | Setup | `null` | Fixed decimal precision |
| `trackLabel(string $label)` | Setup | `null` | Label shown inside the track |
| `size(string $size)` | Setup | `'md'` | `sm`, `md`, `lg` |

---

### Real-world examples

#### Volume Control
```php
TrackSlider::make('volume')
    ->trackLabel('Volume')
    ->min(0)
    ->max(100)
    ->variant('secondary')
    ->size('lg');
```

#### Fine-tuned Spacing
```php
TrackSlider::make('spacing')
    ->min(0)
    ->max(1)
    ->step(0.01)
    ->integer(false)
    ->decimalPlaces(2)
    ->showOutput(false);
```

---

### Playground

`/admin/flex-fields/track-slider`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [FlexSlider](/docs/flexslider) | Traditional slider with draggable handles |
| [NumberStepper](/docs/numberstepper) | Numeric input with increment/decrement buttons |
| [ProgressBar](/docs/progressbar) | Read-only visual progress indicator |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-track-slider` | Root wrapper |
| `fff-track-slider--{sm\|md\|lg}` | Size variant |
| `fff-track-slider--{variant}` | Visual variant |
| `fff-track-slider__track` | Main interactive bar |
| `fff-track-slider__fill` | Active range indicator |
| `fff-track-slider__thumb` | Subtle indicator (visible on hover/drag) |
| `fff-track-slider__track-label` | Label inside the track |
| `fff-track-slider__output` | Value output inside the track |
| `is-disabled` | Disabled state |
| `is-dragging` | Active drag state |
