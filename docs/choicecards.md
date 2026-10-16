---
title: "ChoiceCards"
description: Single-select card group with rich options, layouts, and Material-style ripple effects.
icon: "square-check"
"canonical": "https://flex-fields.bjanczak.com/docs/choicecards"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/choice-cards.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "ChoiceCards in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/choice-cards-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/choice-cards-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/choice-cards.webp"
"twitter:image:alt": "ChoiceCards in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/choicecards#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/choicecards",
      "name": "ChoiceCards",
      "description": "Single-select card group with rich options, layouts, and Material-style ripple effects.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/choicecards#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/choicecards#article",
      "url": "https://flex-fields.bjanczak.com/docs/choicecards",
      "headline": "ChoiceCards",
      "description": "Single-select card group with rich options, layouts, and Material-style ripple effects.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/choicecards#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/choice-cards-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/choice-cards-motion.mp4#video",
      "name": "ChoiceCards — FLEX Fields demonstration",
      "description": "A recorded demonstration of ChoiceCards in the FLEX Fields playground for Filament. Single-select card group with rich options, layouts, and Material-style ripple effects.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/choice-cards.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT4.067S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/choice-cards-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/choicecards#article"
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
  src="/art/previews/choice-cards-motion.mp4"
  poster="/art/previews/choice-cards.webp"
  width="1920"
  height="1080"
  aria-label="ChoiceCards — FLEX Fields demonstration"
>
  <a href="/art/previews/choice-cards-motion.mp4">Watch the ChoiceCards demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Premium **single-select** card group (radio behavior). Ideal for pricing plans, delivery methods, and feature selection. Supports rich option metadata (descriptions, prices, icons, badges) and multiple responsive layouts.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCards` |
| **State type** | `string\|int\|null` — one option key |
| **Model cast** | `'plan' => 'string'` · `'type' => 'integer'` |
| **FieldType** | `choice_cards` |
| **Playground** | `choice-cards` slug in Flex Fields playground |

---

### Basic usage

#### Pricing plans (Stack layout)

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCards;

ChoiceCards::make('plan')
    ->options([
        'starter' => [
            'label' => 'Starter',
            'description' => 'For individuals',
            'price' => '$5',
            'price_suffix' => '/mo',
        ],
        'pro' => [
            'label' => 'Pro',
            'description' => 'For teams',
            'price' => '$15',
            'price_suffix' => '/mo',
        ],
    ])
    ->default('pro');
```

#### Delivery methods (Media layout)

```php
ChoiceCards::make('delivery')
    ->layout('media')
    ->gridColumns(['default' => 1, 'sm' => 3])
    ->options([
        'standard' => ['label' => 'Standard', 'icon' => 'heroicon-o-truck'],
        'express' => ['label' => 'Express', 'icon' => 'heroicon-o-bolt'],
        'rocket' => ['label' => 'Rocket', 'icon' => 'heroicon-o-rocket'],
    ]);
```

---

### State & validation

#### Stored value

State is the **option key** from `options()`.

```php
$record->plan; // string|null — e.g. 'pro'
```

#### Validation rules (built-in)

| Rule | When |
|------|------|
| `nullable` | Always (unless `required()`) |
| Allowed option check | Only a configured, enabled option key is accepted |
| `required` | When `->required()` |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `options(array\|Closure $options)` | Setup | `[]` | Rich option map. Keys: `label`, `description`, `price`, `price_suffix`, `icon`, `badge`, `badge_color`, `disabled`. |
| `disabledOptions(array\|Closure $keys)` | Setup | `[]` | Keys that cannot be selected. |
| `layout(string\|Closure $layout)` | Setup | `'stack'` | `stack`, `grid`, `media`, `featured`. |
| `gridColumns(int\|array\|Closure $cols)` | Setup | `1` | Responsive columns for `grid`/`media` (max 4). Media layouts reduce the column count when the field is too narrow for readable cards. Prices remain aligned on the trailing side while descriptions wrap. |
| `indicator(string\|Closure\|null $type)` | Setup | `auto` | Selection marker: `check` (default except media layouts), `radio` (optional dot), `checkbox`, `none`. The default check shares its appearance and animation with Choice Checkbox Cards. |
| `variant(string\|Closure $variant)` | Setup | `'default'` | `default`, `primary` (stronger selection), `secondary`. |
| `color(string\|Closure\|null $color)` | Setup | `'primary'` | Accent color for selection borders/indicators. |
| `size(string\|Closure $size)` | Setup | `'md'` | Padding and typography scale: `sm`, `md`, `lg`. |
| `ripple(bool\|Closure $on)` | Setup | `false` | Enable Material-style click ripple. |

---

### Real-world examples

#### Featured plan selector

```php
ChoiceCards::make('tier')
    ->layout('featured')
    ->variant('primary')
    ->indicator('check')
    ->options([
        'basic' => ['label' => 'Basic', 'price' => '$0'],
        'pro' => [
            'label' => 'Pro', 
            'price' => '$29', 
            'badge' => 'Recommended',
            'badge_color' => 'success'
        ],
    ]);
```

#### Payment method selector

```php
ChoiceCards::make('payment_method')
    ->layout('media')
    ->gridColumns(2)
    ->options([
        'visa' => ['label' => '**** 1234', 'icon' => 'heroicon-o-credit-card'],
        'paypal' => ['label' => 'PayPal', 'icon' => 'heroicon-o-circle-stack'],
    ]);
```

---

### Playground

`/admin/flex-fields/choice-cards`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [ChoiceCheckboxCards](/docs/choicecheckboxcards) | For multi-select card groups. |
| [FlexChecklist](/docs/flexchecklist) | For a more compact multi-select list. |
| [ItemCardGroup](/docs/itemcardgroup) | For grouping rows that aren't necessarily selectable form fields. |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-choice-cards` | Root wrapper |
| `fff-choice-cards--layout-{stack\|grid\|media\|featured}` | Layout modifier |
| `fff-choice-cards--variant-{default\|primary\|secondary}` | Visual variant |
| `fff-choice-cards--{sm\|md\|lg}` | Size modifier |
| `fff-choice-card` | Individual card element |
| `fff-choice-card--selected` | Active card state |
| `fff-choice-card--disabled` | Disabled card state |
| `fff-choice-card__indicator` | Selection marker wrapper |
| `fff-choice-card__ripple` | Ripple animation element |

### Keyboard, direction and safe values

Use **Tab** to reach the control, **Space** or **Enter** to select, and arrow keys
or **Home / End** to navigate enabled options. In a single-selection group,
arrow navigation also changes the value. Multiple-selection groups move focus
without changing selections. Horizontal navigation follows the text direction.

Direction is inherited from the page, or can be set on this field:

```php
->extraAttributes(['dir' => 'rtl'])
```

Validation checks the submitted values on the server. Unknown or disabled option
keys, nested values, duplicate selections and associative selection arrays are
rejected. Numeric identifiers travel as strings to preserve leading zeros and
large IDs; an Eloquent integer cast can still store an integer column.
