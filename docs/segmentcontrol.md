---
title: "SegmentControl"
description: Single-select segmented control with sliding indicators, icons, and animated label expansion.
icon: "square-split-horizontal"
"canonical": "https://flex-fields.bjanczak.com/docs/segmentcontrol"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/segment-control.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "SegmentControl in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/segment-control-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/segment-control-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/segment-control.webp"
"twitter:image:alt": "SegmentControl in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/segmentcontrol#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/segmentcontrol",
      "name": "SegmentControl",
      "description": "Single-select segmented control with sliding indicators, icons, and animated label expansion.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/segmentcontrol#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/segmentcontrol#article",
      "url": "https://flex-fields.bjanczak.com/docs/segmentcontrol",
      "headline": "SegmentControl",
      "description": "Single-select segmented control with sliding indicators, icons, and animated label expansion.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/segmentcontrol#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/segment-control-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/segment-control-motion.mp4#video",
      "name": "SegmentControl — FLEX Fields demonstration",
      "description": "A recorded demonstration of SegmentControl in the FLEX Fields playground for Filament. Single-select segmented control with sliding indicators, icons, and animated label expansion.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/segment-control.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.917S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/segment-control-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/segmentcontrol#article"
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
  src="/art/previews/segment-control-motion.mp4"
  poster="/art/previews/segment-control.webp"
  width="1920"
  height="1080"
  aria-label="SegmentControl — FLEX Fields demonstration"
>
  <a href="/art/previews/segment-control-motion.mp4">Watch the SegmentControl demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

**Single-select** segmented control — alternative to radio lists or selects, with sliding animations, ghost variants, and optional icon-only modes.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\SegmentControl` |
| **State type** | `string\|int` — one option key |
| **Model cast** | `'status' => 'string'` · `'type' => 'integer'` |
| **FieldType** | `segment_control` |
| **Playground** | `segment-control` slug in Flex Fields playground |

---

### Basic usage

#### Standard segments

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SegmentControl;

SegmentControl::make('status')
    ->options([
        'draft' => 'Draft',
        'review' => 'In Review',
        'published' => 'Published',
    ]);
```

#### Ghost variant with icons and full width

```php
SegmentControl::make('alignment')
    ->label('Text Alignment')
    ->options([
        'left' => 'Left',
        'center' => [
            'label' => 'Center',
            'icon' => 'heroicon-o-bars-3',
            'tooltip' => 'Centered text',
        ],
        'right' => 'Right',
    ])
    ->icons([
        'left' => 'heroicon-o-bars-3-bottom-left',
        'right' => 'heroicon-o-bars-3-bottom-right',
    ])
    ->variant('ghost')
    ->fullWidth();
```

---

### State & validation

#### Stored value

State is the **option key** from `options()`.

```php
$record->status; // string|null — e.g. 'published'
```

#### Validation rules (built-in)

| Rule | When |
|------|------|
| `nullable` | Always (unless `required()`) |
| `Rule::in(...)` | Value must match a configured option key |
| `required` | When `->required()` |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `options(array\|Closure $options)` | Setup | `[]` | Option keys => labels or rich arrays (`label`, `icon`, `disabled`, `tooltip`). |
| `icons(array\|Closure $icons)` | Setup | `[]` | Map of option key => Heroicon name. |
| `disabledOptions(array\|Closure $keys)` | Setup | `[]` | Keys that cannot be selected. |
| `variant(string\|Closure $variant)` | Setup | `'default'` | Visual variant: `default` (filled track), `ghost` (transparent track). |
| `color(string\|Closure\|null $color)` | Setup | `null` | Selection accent color. Defaults to `primary` for `ghost`. |
| `separators(bool\|Closure $condition = true)` | Setup | `true` | Vertical dividers between segments. |
| `fullWidth(bool\|Closure $condition = true)` | Setup | `false` | Stretch the control to full field width. |
| `iconOnly(bool\|Closure $condition = true)` | Setup | `false` | Hide labels; show icons only. |
| `expandSelectedLabel(bool\|Closure $condition = true)` | Setup | `false` | Animate the selected segment to a wider width. |
| `size(string\|ControlSize\|Closure $size)` | Setup | `'md'` | Control size: `sm`, `md`, `lg`. |

#### Public helper methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getOptionKeys()` | `array<int, string\|int>` | Valid keys for validation. |
| `getNormalizedOptions()` | `array` | Resolved options with metadata. |
| `getVariant()` | `string` | Current variant. |
| `getColor()` | `?string` | Resolved accent color. |

---

### Real-world examples

#### Responsive device selector (Icon only)

```php
SegmentControl::make('preview_device')
    ->iconOnly()
    ->options([
        'desktop' => ['icon' => 'heroicon-o-computer-desktop', 'label' => 'Desktop'],
        'tablet' => ['icon' => 'heroicon-o-device-tablet', 'label' => 'Tablet'],
        'phone' => ['icon' => 'heroicon-o-device-phone-mobile', 'label' => 'Phone'],
    ])
    ->default('desktop');
```

#### Date range selector (Ghost variant)

```php
SegmentControl::make('date_range')
    ->variant('ghost')
    ->separators(false)
    ->options([
        '1w' => '1W',
        '4w' => '4W',
        '1y' => '1Y',
        'all' => 'ALL',
    ])
    ->default('4w');
```

---

### Playground

`/admin/flex-fields/segment-control`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [SegmentTabs](/docs/segmenttabs) | When segments should switch between different form schemas. |
| [NpsField](/docs/nps-field) | Survey-specific scales (0–10) with survey semantics. |
| [ChoiceCards](/docs/choicecards) | Larger, card-style selection surfaces. |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-segment-control` | Root wrapper |
| `fff-segment-control--variant-{default\|ghost}` | Visual variant |
| `fff-segment-control--{sm\|md\|lg}` | Size variant |
| `fff-segment-control--full-width` | Full width modifier |
| `fff-segment-track` | Options container |
| `fff-segment-indicator` | Animated selection background |
| `fff-segment-item` | Individual segment wrapper |
| `fff-segment-item--selected` | Active segment state |
| `fff-segment-item--disabled` | Disabled segment state |

## Shared rendering and overflow

Control, Tabs and locale tabs use one renderer and one lifecycle. The SSR strip is
styled before Alpine starts. A constrained field host enables automatic horizontal
scrolling, fading edges and chevrons when options exceed available width. Selection
is scrolled into view, including RTL layouts. `fullWidth()` distributes choices
instead. Arrow keys, Home and End skip disabled choices. No duplicated template or
observer is required when using the field inside `CompositeField`.
