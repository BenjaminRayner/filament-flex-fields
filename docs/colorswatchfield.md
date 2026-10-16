---
title: "ColorSwatchField"
description: "Preset color picker: horizontal swatch pills with optional section header and tooltips."
icon: "swatch-book"
"canonical": "https://flex-fields.bjanczak.com/docs/colorswatchfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/color-swatch.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "ColorSwatchField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/color-swatch-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/color-swatch-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/color-swatch.webp"
"twitter:image:alt": "ColorSwatchField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/colorswatchfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/colorswatchfield",
      "name": "ColorSwatchField",
      "description": "Preset color picker: horizontal swatch pills with optional section header and tooltips.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/colorswatchfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/colorswatchfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/colorswatchfield",
      "headline": "ColorSwatchField",
      "description": "Preset color picker: horizontal swatch pills with optional section header and tooltips.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/colorswatchfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/color-swatch-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/color-swatch-motion.mp4#video",
      "name": "ColorSwatchField — FLEX Fields demonstration",
      "description": "A recorded demonstration of ColorSwatchField in the FLEX Fields playground for Filament. Preset color picker: horizontal swatch pills with optional section header and tooltips.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/color-swatch.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.45S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/color-swatch-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/colorswatchfield#article"
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
  src="/art/previews/color-swatch-motion.mp4"
  poster="/art/previews/color-swatch.webp"
  width="1920"
  height="1080"
  aria-label="ColorSwatchField — FLEX Fields demonstration"
>
  <a href="/art/previews/color-swatch-motion.mp4">Watch the ColorSwatchField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Preset color picker featuring horizontal swatch pills with optional section headers and hover tooltips. Ideal for theme selection, category coloring, or any case where users choose from a predefined palette.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\ColorSwatchField` |
| **State type** | `string\|null` — selected color **key** (not hex) |
| **FieldType** | `color_presets` |
| **Playground** | `color-swatch` slug in Flex Fields playground |

---

### Basic usage

#### Standard Theme Colors
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ColorSwatchField;

ColorSwatchField::make('theme_color')
    ->colors([
        'indigo' => '#6366f1',
        'rose' => '#f43f5e',
        'emerald' => '#10b981',
    ])
    ->sectionLabel('Brand colors')
    ->tooltips(true);
```

#### Large Swatches with Custom Tooltips
```php
ColorSwatchField::make('accent')
    ->colors(['primary' => '#3b82f6', 'white' => '#ffffff'])
    ->tooltips([
        'primary' => 'Primary Brand Blue',
        'white' => 'Pure White',
    ])
    ->size('lg');
```

---

### State & validation

#### Stored value
State is the **array key** from `colors()`, not the hex value. This allows you to change the hex values in your code without breaking existing database records.

```php
$record->theme_color; // 'indigo'
```

#### Validation rules (built-in)
| Rule | Detail |
|------|--------|
| `nullable` | No selection allowed (unless `required()`) |
| `string` | Rejects arrays, objects and numeric payloads |
| `Rule::in(...)` | Key must exist in the configured `colors()` array |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `colors(array\|Closure $colors)` | Setup | `[]` | Map of `key => hex` (or CSS color) |
| `sectionLabel(string\|Closure\|null $label)` | Setup | `null` | Heading above swatches |
| `sectionIcon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | Setup | config | Icon next to section label |
| `tooltips(bool\|array\|Closure $tooltips)` | Setup | `false` | `true` for auto-labels, or custom array |
| `size(string $size)` | Setup | `'md'` | `sm`, `md`, `lg` |

---

### Real-world examples

#### Status Color Picker
```php
ColorSwatchField::make('status_color')
    ->colors([
        'danger' => '#ef4444',
        'warning' => '#f59e0b',
        'success' => '#10b981',
        'info' => '#3b82f6',
    ])
    ->tooltips(true)
    ->required();
```

#### Compact Palette
```php
ColorSwatchField::make('tag_color')
    ->colors($this->getAvailableColors())
    ->size('sm')
    ->hiddenLabel();
```

---

### Interaction, contrast and lifecycle

Tooltip labels use compact 4px vertical and 8px horizontal padding.

White and black swatches keep a visible edge in both themes. The selected ring uses
an independent contrasting color, so selection remains visible even when the swatch
matches the page background. The initial selection, radio semantics and tab stop
are rendered by PHP; there is no introductory selection animation on reload.

- Tab enters the group once, at the selected option (or the first option).
- Arrow keys select and focus the next or previous color, wrapping at the ends.
- RTL reverses horizontal arrows. Home and End select the first and last option.
- Space and Enter activate the focused swatch through its native button.
- `disabled()` renders disabled buttons and prevents changes. `required()` rejects an empty value.
- Reduced motion disables selection and hover transitions.

Each rendered field owns its stylesheet and small JS entry through the FLEX asset
runtime. It has no global listeners, timers, polling or network requests for colors.
Dynamic palettes and labels are evaluated on the server. Removed keys must be
replaced or cleared in your application; they fail validation if submitted.

Palette values accept CSS colors, including hex, named colors, RGB/HSL and CSS
variables. Empty keys/colors and CSS declaration delimiters are rejected. A palette
is developer configuration, not arbitrary CSS from submitted form data.

```php
ColorSwatchField::make('accent')
    ->colors(fn (): array => ['white' => '#fff', 'black' => '#000'])
    ->tooltips(['white' => __('White'), 'black' => __('Black')])
    ->default('white')
    ->required();

ColorSwatchField::make('locked_accent')
    ->colors(['white' => '#fff', 'black' => '#000'])
    ->default('black')
    ->disabled();
```

### Playground

`/admin/flex-fields/color-swatch`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [FlexColorPickerField](/docs/flexcolorpickerfield) | Full HSV/Grid color picker for any color |
| [ChoiceCards](/docs/choicecards) | Large card-style selection |
| [NpsField](/docs/nps-field) | Survey-style color-coded scales |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-color-swatch` | Root wrapper |
| `fff-color-swatch--{sm\|md\|lg}` | Size variant |
| `fff-color-swatch__header` | Section label container |
| `fff-color-swatch__pills` | Swatch pills container |
| `fff-color-swatch__pill` | Individual color button |
| `is-selected` | Selected state |
| `is-disabled` | Disabled state |
