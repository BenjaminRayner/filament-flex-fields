---
title: "RatingField"
description: Star or custom icon rating input with hover preview, semantic colors, and fractional read-only display.
icon: "star"
"canonical": "https://flex-fields.bjanczak.com/docs/ratingfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/rating.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "RatingField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/rating-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/rating-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/rating.webp"
"twitter:image:alt": "RatingField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/ratingfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/ratingfield",
      "name": "RatingField",
      "description": "Star or custom icon rating input with hover preview, semantic colors, and fractional read-only display.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/ratingfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/ratingfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/ratingfield",
      "headline": "RatingField",
      "description": "Star or custom icon rating input with hover preview, semantic colors, and fractional read-only display.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/ratingfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/rating-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/rating-motion.mp4#video",
      "name": "RatingField — FLEX Fields demonstration",
      "description": "A recorded demonstration of RatingField in the FLEX Fields playground for Filament. Star or custom icon rating input with hover preview, semantic colors, and fractional read-only display.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/rating.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT4.133S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/rating-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/ratingfield#article"
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
  src="/art/previews/rating-motion.mp4"
  poster="/art/previews/rating.webp"
  width="1920"
  height="1080"
  aria-label="RatingField — FLEX Fields demonstration"
>
  <a href="/art/previews/rating-motion.mp4">Watch the RatingField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Interactive rating input for scores, feedback, and reviews. Supports custom icons (stars, hearts, etc.), semantic colors, and various sizes. Includes a **read-only mode** that supports fractional values (e.g., 3.7) with partial icon fill.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\RatingField` |
| **State type** | `int\|null` (interactive) · `float\|null` (read-only display) |
| **Model cast** | `'rating' => 'integer'` · `'average_score' => 'float'` |
| **FieldType** | `rating` |
| **Playground** | `rating` slug in Flex Fields playground |

Shares its icon rendering and fractional fill logic with [RatingColumn](/docs/ratingcolumn). The field uses `icon()`, `color()` and `size()`; the column uses `ratingIcon()`, `ratingColor()` and `ratingSize()`.

---

### Basic usage

#### Standard 5-star rating

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RatingField;

RatingField::make('score')
    ->label('How would you rate this product?')
    ->required();
```

#### Custom icon and color

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RatingField;
use Filament\Support\Icons\Heroicon;

RatingField::make('score')
    ->icon(Heroicon::Heart)
    ->color('danger')
    ->stars(10);
```

#### Read-only fractional display

```php
RatingField::make('average_score')
    ->label('Average rating')
    ->readOnly()
    ->default(3.7);
```

---

### State & validation

#### Stored value

In interactive mode, the state is an **integer** representing the number of selected items. In read-only mode, it can be a **float**.

```php
$record->score; // int|null — e.g. 4
$record->average_score; // float|null — e.g. 4.2
```

#### Validation rules (built-in)

| Rule | When |
|------|------|
| `nullable` | Always (unless `required()`) |
| `numeric` | Always |
| `min:0` | Always |
| `max:{stars}` | Matches `stars()` / `max()` |
| `integer` | Interactive mode only |
| `required` | When `->required()` |

### Validation, interaction and storage

Submitted values must be finite numbers within `0..max()`. Interactive ratings must be whole numbers. Arrays, booleans, nested data, malformed strings and nonfinite values are rejected. Numeric strings are normalized to integer values in interactive mode and floats in read-only mode. Empty optional values remain `null`; add `required()` to require a value.

Zero is a valid saved rating, although clicking icons selects values from 1 through `max()`. If at least one star is mandatory, add `rules(['min:1'])` as well as `required()`.

```php
RatingField::make('score')
    ->stars(5)
    ->required()
    ->rules(['min:1']);
```

Read-only mode supports fractional values but is an interaction setting, not an authorization rule. Its submitted data is still validated. For a computed average that must never be overwritten by form input, use `->readOnly()->dehydrated(false)` and calculate the average in your application. Disabled fields are normally excluded from saved form data.

```php
RatingField::make('average')
    ->readOnly()
    ->dehydrated(false)
    ->default(3.7);
```

Use an Eloquent `integer` cast for selected ratings and a `float` cast for averages. In a custom form, obtain validated data with `$this->form->getState()` before saving it.

Tab focuses the selected rating, or the first icon when empty. Arrow keys select adjacent values; horizontal direction follows RTL. Home/End select the first/last value. Space and Enter select the focused icon. Hover changes the preview without saving; leaving, cancelling a pointer or receiving external state restores the saved preview. Disabled and read-only ratings cannot be changed through these controls.

The field emits bubbling browser `input` and `change` events with `event.detail.value` after a changed selection. Use `live()` with `afterStateUpdated()` for server-side change callbacks. Default binding defers state until the next form request. Selecting the current value again does not clear it.

Fractional read-only ratings and table columns load shared styles only. Interactive fields load their JS on activation. They use no continuous animation loop or resize observer; icons adapt to layout changes through CSS.

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `stars(int\|Closure $count)` | Setup | `5` | Number of rating items, from 1 to 100. Alias: `max()`. |
| `icon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | Setup | `Heroicon::Star` | Custom icon for every item. |
| `color(string\|Closure\|null $color)` | Setup | `warning` | Semantic fill color for active icons. |
| `size(string\|ControlSize\|Closure $size)` | Setup | `md` | Control size: `sm`, `md`, `lg`. |
| `readOnly(bool\|Closure $condition = true)` | Setup | `false` | Display-only mode with fractional support. |
| `showValue(bool\|Closure $condition = true)` | Setup | `true` | Show numeric value beside icons in read-only mode. |
| `extraAlpineAttributes(array\|Closure $attributes)` | Setup | `[]` | Extra Alpine bindings on the root element. |

#### Public helper methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getFillPercentageForValue(float\|int\|null $value, int $index)` | `float` | Fill ratio `0.0–1.0` for icon at 1-based `$index`. |

---

### Real-world examples

#### Feedback form with required rating

```php
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Textarea;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        Section::make('Customer Feedback')->schema([
            RatingField::make('satisfaction_score')
                ->label('Overall Satisfaction')
                ->stars(5)
                ->live()
                ->required(),
            
            Textarea::make('feedback_text')
                ->label('Comments')
                ->visible(fn (Get $get) => $get('satisfaction_score') <= 3),
        ]),
    ]);
}
```

#### Product list with average rating

```php
RatingField::make('avg_rating')
    ->readOnly()
    ->size('sm')
    ->color('primary')
    ->default(fn ($record) => $record->reviews()->avg('rating'));
```

---

### Playground

`/admin/flex-fields/rating`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [RatingColumn](/docs/ratingcolumn) | Read-only rating display in tables. |
| [NpsField](/docs/nps-field) | Survey-specific scales (0–10) with color coding. |
| [SegmentControl](/docs/segmentcontrol) | Generic single-select segments. |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-rating` | Root wrapper |
| `fff-rating--{sm\|md\|lg}` | Size variant |
| `fff-rating__items` | Icons container |
| `fff-rating__item` | Individual icon wrapper |
| `fff-rating__value` | Numeric value (read-only) |
