---
title: "IconPickerField"
description: Searchable blade-icons picker with lazy SVG rendering, set filters, and virtual scrolling.
icon: "shapes"
"canonical": "https://flex-fields.bjanczak.com/docs/icon-picker-field"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/icon-picker-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "IconPickerField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/icon-picker-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/icon-picker-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/icon-picker-field.webp"
"twitter:image:alt": "IconPickerField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/icon-picker-field#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/icon-picker-field",
      "name": "IconPickerField",
      "description": "Searchable blade-icons picker with lazy SVG rendering, set filters, and virtual scrolling.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/icon-picker-field#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/icon-picker-field#article",
      "url": "https://flex-fields.bjanczak.com/docs/icon-picker-field",
      "headline": "IconPickerField",
      "description": "Searchable blade-icons picker with lazy SVG rendering, set filters, and virtual scrolling.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/icon-picker-field#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/icon-picker-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/icon-picker-field-motion.mp4#video",
      "name": "IconPickerField — FLEX Fields demonstration",
      "description": "A recorded demonstration of IconPickerField in the FLEX Fields playground for Filament. Searchable blade-icons picker with lazy SVG rendering, set filters, and virtual scrolling.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/icon-picker-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT4.6S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/icon-picker-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/icon-picker-field#article"
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
  src="/art/previews/icon-picker-field-motion.mp4"
  poster="/art/previews/icon-picker-field.webp"
  width="1920"
  height="1080"
  aria-label="IconPickerField — FLEX Fields demonstration"
>
  <a href="/art/previews/icon-picker-field-motion.mp4">Watch the IconPickerField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Searchable **blade-icons** picker with lazy SVG rendering, set filters, whitelist/exclude controls, paginated server-side search, and virtual scrolling for large catalogs. Stores the full icon name string (e.g. `hugeicons-star`).

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField` |
| **State type** | `string\|null` |
| **FieldType** | `icon_picker` |
| **Playground** | `icon-picker-field` slug in Flex Fields playground |

The stored value is a plain string, making it compatible with any standard Filament or Blade icon component.

---

### Basic usage

#### Standard Icon Picker
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;

IconPickerField::make('menu_icon')
    ->label('Menu icon')
    ->sets(['heroicons', 'hugeicons'])
    ->preload()
    ->required();
```

#### Strict Whitelist
```php
IconPickerField::make('status_icon')
    ->sets(['heroicons'])
    ->icons([
        'heroicon-o-check-circle',
        'heroicon-o-exclamation-triangle',
        'heroicon-o-x-circle',
    ])
    ->gridColumns(4)
    ->size('sm');
```

---

### State & validation

#### Stored value
State is the full blade-icons name stored as a plain string.

```php
$record->menu_icon; // 'heroicon-o-star'
```

#### Validation rules (built-in)
Custom rule checks the value against the configured catalog (sets, whitelist, and exclude list).

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `sets(array\|string $sets)` | Setup | all | Limit available icon libraries |
| `icons(array $icons)` | Setup | — | Whitelist only these full icon names |
| `excludeIcons(array $icons)` | Setup | — | Remove icons from the catalog |
| `searchResultsLayout(string $layout)` | Setup | `icons` | `icons`, `grid`, or `list` |
| `gridColumns(int $columns)` | Setup | `8` | Column count for grid layouts (2–12) |
| `preload(bool $condition)` | Setup | `false` | Fetch first page on mount |
| `closeOnSelect(bool $condition)` | Setup | `true` | Close panel after selection |
| `perPage(int $perPage)` | Setup | `64` | Server page size (max 96) |
| `limitPerSet(int $limit)` | Setup | — | Cap icons indexed per set |
| `clearable(bool $condition)` | Setup | `true` | Show clear (×) button |
| `variant(string $variant)` | Setup | `bordered` | `bordered`, `secondary`, `flat`, `soft`, `faded`, `underlined` |
| `size(string $size)` | Setup | `'md'` | `sm`, `md`, `lg` |
| `placeholder(string $text)` | Setup | default | Trigger placeholder |

---

### Real-world examples

#### CMS Navigation Icon
```php
IconPickerField::make('icon')
    ->label('Navigation Icon')
    ->sets(['heroicons'])
    ->iconsOnly()
    ->gridColumns(10)
    ->preload();
```

#### Frontend Usage (Blade)
```blade
@if (filled($category->icon))
    <x-icon :name="$category->icon" class="h-6 w-6" />
@endif
```

---

### Playground

`/admin/flex-fields/icon-picker-field`

See [Playground](/docs/index#playground) for setup.

---

### Ops notes

`getIconPickerSearchResults` and `getIconPickerSvgPreviews` share the Select-family Livewire rate limit (`filament-flex-fields.select.search_rate_limit_per_minute`). Keys use the authenticated user id or `Request::ip()` — configure Laravel **TrustedProxies** behind a reverse proxy. SVG preview batches stay capped at 64 icons. When limited, search returns an empty page (`hasMore=false`) and SVG previews return `[]`.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [IconColumn](/docs/iconcolumn) | Read-only icon display in tables |
| [SelectField](/docs/selectfield) | Generic selection without icon previews |
| [RatingField](/docs/ratingfield) | Star-based rating input |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-icon-select` | Shared picker controller |
| `fff-icon-picker-field` | Standalone field wrapper |
| `fff-icon-select--inline` | Text-input toolbar presentation |
| `fff-icon-select__preview` | Selected SVG preview |
| `fff-icon-select__name` | Selected icon name |
| `fff-icon-select__menu` | Teleported dropdown panel |


## Choose an icon beside text

Use `FlexTextInput::iconPicker()` to place the same searchable icon picker in a
text input's toolbar. The label and icon remain separate form values. The inline
menu aligns with the end of the input, opens above when needed, and uses the
shared mobile sheet on small screens. An empty value shows the Hugeicons shapes
symbol; it does not write a default icon into your data.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;

FlexTextInput::make('label')
    ->iconPicker('icon', fn (IconPickerField $picker) => $picker
        ->sets(['hugeicons'])
        ->perPage(48)
        ->clearable());
```

The callback accepts the same configuration methods documented on this page,
including `default()` for an initial value. The `icon` state path is relative to
the surrounding form or repeater row. Persist both values using your normal
Filament form save flow. See [Text input: associated icon picker](/flextextinput#associated-icon-picker)
for storage, validation and composition examples.

`inlineTrigger()` selects the compact trigger presentation for composed controls.
When using a text input, it is applied automatically.

When embedded through `FlexTextInput::iconPicker()`, clearing is enabled by default.
The dropdown's **Clear icon** button resets only the associated icon to `null`, leaving
the text unchanged. Set `clearable(false)` in the callback to disable the action;
`required()` still rejects an empty selection during form validation. The action
is unavailable for disabled or read-only inputs and after the picker is disposed.
