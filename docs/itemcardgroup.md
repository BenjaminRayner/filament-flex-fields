---
title: "ItemCardGroup"
description: Grouped list surface for multiple ItemCard rows with shared containers, headers, and row separators.
icon: "gallery-horizontal-end"
"canonical": "https://flex-fields.bjanczak.com/docs/itemcardgroup"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/item-card-group.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "ItemCardGroup in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/item-card-group-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/item-card-group-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/item-card-group.webp"
"twitter:image:alt": "ItemCardGroup in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/itemcardgroup#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/itemcardgroup",
      "name": "ItemCardGroup",
      "description": "Grouped list surface for multiple ItemCard rows with shared containers, headers, and row separators.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/itemcardgroup#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/itemcardgroup#article",
      "url": "https://flex-fields.bjanczak.com/docs/itemcardgroup",
      "headline": "ItemCardGroup",
      "description": "Grouped list surface for multiple ItemCard rows with shared containers, headers, and row separators.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/itemcardgroup#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/item-card-group-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/item-card-group-motion.mp4#video",
      "name": "ItemCardGroup — FLEX Fields demonstration",
      "description": "A recorded demonstration of ItemCardGroup in the FLEX Fields playground for Filament. Grouped list surface for multiple ItemCard rows with shared containers, headers, and row separators.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/item-card-group.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.2S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/item-card-group-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/itemcardgroup#article"
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
  src="/art/previews/item-card-group-motion.mp4"
  poster="/art/previews/item-card-group.webp"
  width="1920"
  height="1080"
  aria-label="ItemCardGroup — FLEX Fields demonstration"
>
  <a href="/art/previews/item-card-group-motion.mp4">Watch the ItemCardGroup demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Grouped list surface that wraps multiple `ItemCard` rows in a single bordered container. Features optional group headers, row separators, group-level variants, and group-wide pressable row behavior.

Selects, switches and actions inside a row use their original components and
validation. A row containing controls remains a regular container, so clicking
the control does not also activate the row. The shared card stylesheet is loaded
once; the ripple module is requested only by pressable rows. Reduced-motion
preferences disable the ripple.

RTL follows the page direction, or can be enabled on a group with
`extraAttributes(['dir' => 'rtl'])`. Text, row actions and chevrons follow the
reading direction. Give sibling groups distinct `key()` values without making
those layout keys part of child field state paths:

```php
->key('account-settings', isInheritable: false)
```

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ItemCardGroup` |
| **State** | None — display/layout component |
| **Playground** | `item-card-group` slug in Flex Fields playground |
| **Extends** | `Filament\Schemas\Components\Component` |

---

### Basic usage

#### Standard settings group

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ItemCard;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ItemCardGroup;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;

ItemCardGroup::make('Account Settings')
    ->description('Manage your profile and security')
    ->separated()
    ->schema([
        ItemCard::make('Profile')->icon(FlexIcon::Person)->chevron(),
        ItemCard::make('Security')->icon(FlexIcon::Key)->chevron(),
    ]);
```

#### Outside header with pressable rows

```php
ItemCardGroup::make('Notifications')
    ->headerStyle('outside')
    ->pressable()
    ->schema([
        ItemCard::make('Email')->description('Manage email alerts'),
        ItemCard::make('Push')->description('Manage mobile alerts'),
    ]);
```

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `heading(string\|Htmlable\|Closure $text)` | Setup | `null` | Group title text. |
| `description(string\|Htmlable\|Closure $text)` | Setup | `null` | Group subtitle text. |
| `schema(array\|Closure $components)` | Setup | `[]` | Child rows (typically `ItemCard` instances). |
| `variant(string\|Closure $variant)` | Setup | `'default'` | Surface style: `default`, `secondary`, `tertiary`, `outline`, `transparent`. |
| `layout(string\|Closure $layout)` | Setup | `'list'` | Child arrangement: `list` or `grid`. Combine `grid` with native `columns()` for responsive columns. |
| `divided(bool\|Closure $on)` | Setup | `false` | Enable horizontal separators between rows. Alias: `separated()`. |
| `withoutSeparators()` | Setup | — | Disable row separators. |
| `headerStyle(string\|Closure $style)` | Setup | `'embedded'` | `embedded` (inside box) or `outside` (above box). |
| `pressable(bool\|Closure $on)` | Setup | `false` | Enable group-wide hover/ripple for child rows. |

#### Public helper methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getLayout()` | `string` | `list` or `grid`. |
| `getVariant()` | `string` | Resolved group variant. |
| `isDivided()` | `bool` | Whether row separators are active. |
| `getHeaderStyle()` | `string` | `embedded` or `outside`. |
| `areRowsPressable()` | `bool` | Group-level pressable flag. |

---

### Real-world examples

#### Mixed controls group

```php
ItemCardGroup::make('Settings')
    ->separated()
    ->schema([
        ItemCard::make('Language')
            ->schema([
                SelectField::make('lang')->options(['en' => 'English'])->variant('item-card'),
            ]),
        ItemCard::make('Dark mode')
            ->schema([
                SwitchField::make('dark')->inline()->size('sm'),
            ]),
    ]);
```

#### Grid layout for cards

```php
ItemCardGroup::make('Quick Actions')
    ->layout('grid')
    ->columns(['default' => 1, 'sm' => 2])
    ->schema([
        ItemCard::make('New Post')->icon(FlexIcon::Plus)->standalone(),
        ItemCard::make('View Stats')->icon(FlexIcon::ChartColumn)->standalone(),
    ]);
```

---

### Playground

`/admin/flex-fields/item-card-group`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [ItemCard](/docs/itemcard) | For individual list rows outside of a group. |
| [ChoiceCards](/docs/choicecards) | When the group represents a selectable form state. |
| [SegmentTabs](/docs/segmenttabs) | For switching between different form schemas using segments. |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `item-card-group` | Main group container |
| `item-card-group-host` | Outside-header wrapper |
| `item-card-group--{layout}` | List or grid layout modifier |
| `item-card-group--{variant}` | Surface variant modifier |
| `item-card-group--divided` | Row separators active |
| `item-card-group__header` | Title and description container |
| `data-slot="item-card-group-surface"` | Outside-header surface wrapper |
| `item-card-group__content` | Child components container |
