---
title: "SegmentTabs"
description: Schema layout component featuring iOS-style segmented tabs with per-tab form schemas and query-string persistence.
icon: "panels-left-bottom"
"canonical": "https://flex-fields.bjanczak.com/docs/segmenttabs"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/segment-tabs.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "SegmentTabs in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/segment-tabs-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/segment-tabs-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/segment-tabs.webp"
"twitter:image:alt": "SegmentTabs in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/segmenttabs#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/segmenttabs",
      "name": "SegmentTabs",
      "description": "Schema layout component featuring iOS-style segmented tabs with per-tab form schemas and query-string persistence.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/segmenttabs#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/segmenttabs#article",
      "url": "https://flex-fields.bjanczak.com/docs/segmenttabs",
      "headline": "SegmentTabs",
      "description": "Schema layout component featuring iOS-style segmented tabs with per-tab form schemas and query-string persistence.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/segmenttabs#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/segment-tabs-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/segment-tabs-motion.mp4#video",
      "name": "SegmentTabs — FLEX Fields demonstration",
      "description": "A recorded demonstration of SegmentTabs in the FLEX Fields playground for Filament. Schema layout component featuring iOS-style segmented tabs with per-tab form schemas and query-string persistence.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/segment-tabs.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.867S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/segment-tabs-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/segmenttabs#article"
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
  src="/art/previews/segment-tabs-motion.mp4"
  poster="/art/previews/segment-tabs.webp"
  width="1920"
  height="1080"
  aria-label="SegmentTabs — FLEX Fields demonstration"
>
  <a href="/art/previews/segment-tabs-motion.mp4">Watch the SegmentTabs demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Schema layout component providing **iOS-style segmented tabs**. Each tab wraps a nested form schema, allowing for clean organization of complex forms. Shares the same visual language as [SegmentControl](/docs/segmentcontrol).

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs` |
| **Tab class** | `Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab` |
| **State** | Local UI state (optional query-string persistence) |
| **Playground** | `segment-tabs` slug in Flex Fields playground |

---

### Basic usage

#### Standard segmented tabs

```php
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

SegmentTabs::make('Account Settings')
    ->tabs([
        SegmentTab::make('General')
            ->icon('heroicon-o-user')
            ->schema([
                FlexTextInput::make('name'),
                FlexTextInput::make('email'),
            ]),
        SegmentTab::make('Security')
            ->icon('heroicon-o-lock')
            ->schema([
                FlexTextInput::make('password'),
            ]),
    ]);
```

#### Ghost variant with query persistence

```php
SegmentTabs::make('Profile')
    ->variant('ghost')
    ->persistTabInQueryString('profile-tab')
    ->tabs([
        SegmentTab::make('Details')->schema([...]),
        SegmentTab::make('Billing')->schema([...]),
    ]);
```

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `tabs(array\|Closure $tabs)` | Setup | `[]` | Array of `SegmentTab` components. |
| `activeTab(int\|Closure $index)` | Setup | `1` | 1-based index of the initially active tab. |
| `persistTabInQueryString(string\|null $key)` | Setup | `null` | Persist active tab in URL. Default key: `'segment-tab'`. |
| `variant(string\|Closure $variant)` | Setup | `'default'` | `default` (filled track) or `ghost` (transparent track). |
| `color(string\|Closure\|null $color)` | Setup | `null` | Selection accent color. Defaults to `primary` for `ghost`. |
| `separators(bool\|Closure $on)` | Setup | `true` | Vertical dividers between tab segments. |
| `fullWidth(bool\|Closure $on)` | Setup | `false` | Stretch tabs to full container width. |
| `iconOnly(bool\|Closure $on)` | Setup | `false` | Hide tab labels; show icons only. |
| `expandSelectedLabel(bool\|Closure $on)` | Setup | `false` | Animate the selected tab to a wider width. |
| `helperText(string\|Htmlable\|Closure\|null $text)` | Setup | `null` | Helper copy below the tab panels (same idea as form fields). |
| `size(string\|Closure $size)` | Setup | `'md'` | Tab rail height at default density: `sm` 32 px, `md` 40 px, `lg` 48 px. |
| `rounding(string\|Closure\|null $rounding)` | Setup | `full` | `none`, `native`, `md`, `lg`, `xl`, `full`; `null` or `default` resolves to `full`. |

#### Tab height and rounding

```php
SegmentTabs::make('Settings')
    ->size('md') // 40 px including track padding
    ->rounding('lg') // 16 px, for this tab switcher only
    ->tabs([...]);
```

Rounding values are `none` (0), `native` (8 px), `md` (12 px), `lg` (16 px),
`xl` (20 px) and `full` (pill). Omitting `rounding()`, passing `null`, or using
`default` uses the full pill radius. Track, active indicator and segment buttons
use the same instance radius. Nested fields retain their own radius and sizes.
The separate SegmentControl form field retains its 32 / 36 / 42 px size scale.

#### SegmentTab API

| Method | Description |
|--------|-------------|
| `make(string\|Htmlable\|Closure $label)` | Create a tab instance. |
| `icon(mixed $icon)` | Tab icon. |
| `tooltip(string\|Closure $text)` | Hover tooltip for the tab. |
| `schema(array\|Closure $schema)` | Nested form/schema components. |
| `badge(string\|Closure $text)` | Optional badge shown on the tab. |

---

### Real-world examples

#### Multi-step profile editor

```php
SegmentTabs::make('User Editor')
    ->variant('ghost')
    ->fullWidth()
    ->tabs([
        SegmentTab::make('Basic Info')
            ->icon('heroicon-o-identification')
            ->schema([
                FlexTextInput::make('first_name'),
                FlexTextInput::make('last_name'),
            ]),
        SegmentTab::make('Preferences')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                Toggle::make('newsletter'),
                Select::make('theme')->options(['light' => 'Light', 'dark' => 'Dark']),
            ]),
    ]);
```

---

### Playground

`/admin/flex-fields/segment-tabs`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [SegmentControl](/docs/segmentcontrol) | For simple single-select form fields without nested schemas. |
| [TranslatableFields](/docs/translatablefields) | For multi-language form fields (uses SegmentTabs internally). |
| [ItemCardGroup](/docs/itemcardgroup) | For grouping rows that don't need tab switching. |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-segment-tabs` | Root layout wrapper |
| `fff-segment-tabs__header` | Tab switcher container |
| `fff-segment-tabs__content` | Active tab panel container |
| `fff-segment-tab-panel` | Individual tab content wrapper |

Uses `fff-segment-control` classes for the tab switcher header.

## Runtime and composition

Tabs reuse SegmentControl presentation rather than copying its HTML. Initially
active child assets are render-blocking; inactive panels load on activation and
retire assets after their last consumer disappears. Local selection survives
Livewire action morphs. Query persistence stores relative tab keys and preserves
Livewire history state. Automatic horizontal overflow provides chevrons and fading
edges; keyboard selection scrolls the active tab into view.
