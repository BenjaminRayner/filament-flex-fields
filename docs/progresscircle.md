---
title: "ProgressCircle"
description: SVG circular progress with full-circle and semi-circle variants, gradient strokes, and optional card shell.
icon: "circle-gauge"
"canonical": "https://flex-fields.bjanczak.com/docs/progresscircle"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/progress-circle.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "ProgressCircle in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/progress-circle-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/progress-circle-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/progress-circle.webp"
"twitter:image:alt": "ProgressCircle in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/progresscircle#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/progresscircle",
      "name": "ProgressCircle",
      "description": "SVG circular progress with full-circle and semi-circle variants, gradient strokes, and optional card shell.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/progresscircle#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/progresscircle#article",
      "url": "https://flex-fields.bjanczak.com/docs/progresscircle",
      "headline": "ProgressCircle",
      "description": "SVG circular progress with full-circle and semi-circle variants, gradient strokes, and optional card shell.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/progresscircle#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/progress-circle-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/progress-circle-motion.mp4#video",
      "name": "ProgressCircle — FLEX Fields demonstration",
      "description": "A recorded demonstration of ProgressCircle in the FLEX Fields playground for Filament. SVG circular progress with full-circle and semi-circle variants, gradient strokes, and optional card shell.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/progress-circle.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT4.717S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/progress-circle-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/progresscircle#article"
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
  src="/art/previews/progress-circle-motion.mp4"
  poster="/art/previews/progress-circle.webp"
  width="1920"
  height="1080"
  aria-label="ProgressCircle — FLEX Fields demonstration"
>
  <a href="/art/previews/progress-circle-motion.mp4">Watch the ProgressCircle demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

High-performance SVG circular progress indicator. Supports full-circle and semi-circle variants, rounded stroke caps, centered or side-aligned content, and advanced gradient strokes for both fill and track.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressCircle` |
| **State** | None — display-only schema component |
| **Playground** | `progress-circle` slug in Flex Fields playground |
| **Stylesheet** | Lazy `progress-circle` bundle |

---

### Basic usage

#### Standard circle

```php
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressCircle;

ProgressCircle::make()
    ->value(75)
    ->displayValue('75%')
    ->color('primary');
```

#### Semicircle with gradient

```php
ProgressCircle::make()
    ->variant('semicircle')
    ->value(69)
    ->gapAngle(24)
    ->gradientFrom('#6366f1')
    ->gradientTo('#ec4899')
    ->label('Completion rate');
```

#### Card layout with side content

```php
ProgressCircle::make()
    ->value(82)
    ->contentLayout('left')
    ->shell()
    ->heading('Storage used')
    ->description('Last 30 days')
    ->footer('Updated 5m ago');
```

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `value(float\|int\|Closure\|null $value)` | Setup | `0` | Arc fill ratio. |
| `max(float\|int\|Closure $max)` | Setup | `100` | Maximum progress value. |
| `displayValue(string\|Closure\|null $value)` | Setup | `null` | Primary center text. Auto-derived from percent when omitted. |
| `showValue(bool\|Closure $condition = true)` | Setup | `true` | Show numeric value/fraction; hiding it preserves accessible progress values. |
| `fraction(string\|Closure\|null $fraction)` | Setup | `null` | Secondary line (e.g., "124 / 200") shown below display value. |
| `label(string\|Closure\|null $label)` | Setup | `null` | Tertiary label shown at the bottom. |
| `variant(string\|Closure $variant)` | Setup | `'circle'` | `circle` (full ring), `semicircle` (open gauge arc), or `breakdown` (distribution gauge). |
| `gapAngle(float\|int\|Closure $degrees)` | Setup | `0` | Degrees of empty gap at the bottom of the arc. |
| `paused(bool\|Closure $condition = true)` | Setup | `false` | Show paused state with center icon instead of text. |
| `pausedIcon(mixed $icon)` | Setup | `PauseFill` | Icon to show when `paused()` is true. |
| `color(string\|Closure\|null $color)` | Setup | `primary` | Semantic token or hex/rgb/hsl stroke color. |
| `size(string\|Closure $size)` | Setup | `'md'` | Ring diameter: `xs` 40px, `sm` 88px, `md` 120px, `lg` 160px. Also accepts `ControlSize`. |
| `gradientFrom(string\|Closure $color)` | Setup | `null` | Start color for progress stroke gradient. |
| `gradientTo(string\|Closure $color)` | Setup | `null` | End color for progress stroke gradient. |
| `gradientStroke(string\|Closure $css)` | Setup | `null` | Compatibility linear-gradient string with exactly two hex/rgb/hsl color stops; prefer gradientFrom/gradientTo. |
| `trackGradientFrom(string\|Closure $color)` | Setup | `null` | Start color for track (unfilled) gradient. |
| `trackGradientTo(string\|Closure $color)` | Setup | `null` | End color for track (unfilled) gradient. |
| `contentLayout(string\|Closure $layout)` | Setup | `'center'` | Text position: `center`, `left`, `right`, `above`. |
| `shell(bool\|Closure $condition = true)` | Setup | `false` | Wrap in a static card container. |
| `heading(string\|Closure $text)` | Setup | `null` | Card heading; creates card chrome for ordinary progress variants. |
| `description(string\|Closure $text)` | Setup | `null` | Optional description. |
| `footer(string\|Closure $text)` | Setup | `null` | Optional footer. |
| `animated(bool\|Closure $condition = true)` | Setup | `true` | Animate fill transitions. |
| `animationDuration(int\|Closure $ms)` | Setup | `240` | Transition duration in milliseconds. |

---

### Real-world examples

#### Dashboard Completion Rate

```php
ProgressCircle::make()
    ->value(fn () => $this->getCompletionRate())
    ->variant('semicircle')
    ->gapAngle(30)
    ->size('lg')
    ->color('success')
    ->label('Profile completion')
    ->shell();
```

#### Storage Usage with Fraction

```php
ProgressCircle::make()
    ->value(18.5)
    ->max(20)
    ->displayValue('92%')
    ->fraction('18.5GB / 20GB')
    ->color('warning')
    ->size('md');
```

---

### Playground

`/admin/flex-fields/progress-circle`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [ProgressBar](/docs/progressbar) | Linear progress for uploads or stepped trackers. |
| [RatingField](/docs/ratingfield) | Star/icon ratings for user feedback. |
| [CoverCard](/docs/covercard) | Media-heavy cards with background images. |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-progress-circle` | Root wrapper |
| `fff-progress-circle--{xs\|sm\|md\|lg}` | Size variant |
| `fff-progress-circle--semicircle` | Semicircle variant modifier |
| `has-shell` | Card shell active |
| `fff-progress-circle--layout-{center\|left\|right\|above}` | Content alignment |
| `fff-progress-circle__svg` | SVG element |
| `fff-progress-circle__track` | Unfilled background arc |
| `fff-progress-circle__fill` | Animated progress arc |
| `fff-progress-circle__center` | Centered text container |
| `fff-progress-circle__display-value` | Primary numeric text |
| `fff-progress-circle__fraction` | Secondary fraction text |
| `fff-progress-circle__label` | Bottom label text |


### Static rendering and responsive content

Ordinary circle and semicircle displays are server-rendered SVG and CSS, with no
Alpine factory, event listeners, ResizeObserver or component JavaScript. Each instance owns its stylesheet consumer
and unique SVG gradient references. CSS animates changed values when enabled;
reduced-motion preferences disable the transition. A zero value displays only
the track, without a colored rounded-cap dot.

Ordinary progress card shells have a 24px radius; distribution cards use 16px. Side layouts reserve space for their text, wrap
within the available width and mirror with surrounding RTL direction. Long center
labels (over 22 characters) appear below the ring so they do not collide with its
stroke. Semicircle and `xs` center labels also appear below. The above layout retains the
value inside the ring. Paused displays use a clear icon without blurred digits
behind it; assistive technology receives the paused state and current value.

The accessible progressbar uses the configured numeric maximum and a clamped
current value; custom displayValue supplies the spoken value text. Set an explicit
aria-label through extraAttributes if a dashboard requires a different accessible
name. Null means zero. Non-finite values, non-positive max, invalid enum choices
and injected CSS declarations are rejected.

### Distribution half gauge

`variant('breakdown')` draws multiple rounded segments across a 180-degree half
ring. It is server-rendered SVG with a small optional preview controller and no chart library.
Amounts determine the segment proportions; the center shows the first largest
segment by default. Hovering an arc or legend entry previews its percentage and
label immediately, with no server request. Leaving restores the default (or a
pinned selection). Keyboard focus previews the same information. Click or tap
pins a segment; Escape clears that selection. Only interactive breakdown instances
load the preview module; ordinary rings and `interactive(false)` remain CSS/SVG-only. `activeSegment()` selects a different center channel by its zero-based
index. `displayValue()` and `label()` can override the center text.

```php
ProgressCircle::make()
    ->variant('breakdown')
    ->shell()
    ->heading('Workload by service')
    ->summaryValue('12,500 jobs')
    ->changeLabel('+12%')
    ->segments([
        ['label' => 'API', 'value' => 6000, 'displayValue' => '6,000', 'color' => '#3b82f6'],
        ['label' => 'Uploads', 'value' => 3500, 'displayValue' => '3,500', 'color' => '#8b5cf6'],
        ['label' => 'Exports', 'value' => 2000, 'displayValue' => '2,000', 'color' => '#f59e0b'],
        ['label' => 'Webhooks', 'value' => 1000, 'displayValue' => '1,000', 'color' => '#14b8a6'],
    ]);
```

| Method | Default | Purpose |
|---|---|---|
| `interactive(bool\|Closure)` | `true` for breakdown | Preview segment percentages on hover or focus; click/tap pins a segment, Escape resets it. `false` loads no interaction module |
| `segments(array\|Closure)` | `[]` | Up to 64 rows; each requires a non-empty string label and a finite non-negative numeric value |
| `activeSegment(int\|Closure\|null)` | largest amount | Center channel index; explicit index must exist |
| `segmentGap(float\|int\|Closure)` | `3` | Gap between positive segments, in degrees (0–12); bounded further for many segments |
| `showLegend(bool\|Closure)` | `true` | Show colored label/amount rows under the gauge |
| `summaryValue(string\|Closure\|null)` | `null` | Formatted headline total; does not change segment calculations |
| `changeLabel(string\|Closure\|null)` | `null` | Static positive-change badge |
| `periodLabel(string\|Closure\|null)` | `null` | Static period chip with calendar icon |

Segment `color` is optional and falls back to a repeating six-color palette.
`displayValue` is optional and defaults to the numeric amount with grouping.
An all-zero or empty dataset draws only the track and reports 0%. Zero amounts
remain in the legend but do not produce colored arcs. Tiny positive segments keep
finite geometry with appropriately reduced corner radii.

This distribution chart uses labeled segments and legend buttons when interactive;
`interactive(false)` exposes a static image and text legend. It does not imply
completion progress. Its period chip and badge are display-only.
Use `showLegend(false)` for a compact chart. Gauge widths are `xs` 144px, `sm`
200px, `md` 240px and `lg` 288px (bounded by the available width), and omit `shell()` to embed it into your own card. Channel proportions are
calculated from their sum; `value()`/`max()` apply to the ordinary progress variants.

```php
ProgressCircle::make()
    ->variant('breakdown')
    ->size('sm')
    ->activeSegment(1)
    ->showLegend(false)
    ->segmentGap(4)
    ->segments([
        ['label' => 'Photos', 'value' => 32, 'displayValue' => '32 GB'],
        ['label' => 'Videos', 'value' => 40, 'displayValue' => '40 GB'],
        ['label' => 'Documents', 'value' => 8, 'displayValue' => '8 GB'],
    ]);
```


### Small indicators in a table

`size('xs')` draws a 40px ring. Use `showValue(false)` for the ring alone, without
removing its accessible numeric value. The ordinary ring sizes apply to circle
and semicircle variants; breakdown gauges use the wider scales listed above.

```php
ProgressCircle::make()->value(68)->size('xs')->showValue(false);
```

ProgressCircle is a schema display, not a table Column class. Embed the same
component inside a native Filament `ViewColumn`, so its SVG and per-cell asset
consumer stay shared. The existing FLEX `ProgressColumn` is a linear indicator.

```php
use Filament\Tables\Columns\ViewColumn;

ViewColumn::make('completion')
    ->label('Completion')
    ->view('tables.columns.completion-circle');
```

Create `resources/views/tables/columns/completion-circle.blade.php`:

```blade
@php
    $circle = \Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressCircle::make()
        ->key('completion-circle-'.data_get($getRecord(), 'id'))
        ->value((float) ($getState() ?? 0))
        ->max(100)
        ->size('xs')
        ->color('success')
        ->extraAttributes(['aria-label' => 'Completion']);
@endphp

{{ \Filament\Schemas\Schema::make($getLivewire())->components([$circle]) }}
```

This example expects a numeric percentage in `completion`. For counts, configure
`max()` from the record and optionally `displayValue()` with your own short text.
No copied SVG, inline stylesheet, or extra JavaScript is needed. Keep headings,
card shells and long labels outside a compact table cell.
