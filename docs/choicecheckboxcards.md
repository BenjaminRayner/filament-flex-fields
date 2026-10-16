---
title: "ChoiceCheckboxCards"
description: Multi-select card group with rich options, selection limits, and animated checkmark indicators.
icon: "list-checks"
"canonical": "https://flex-fields.bjanczak.com/docs/choicecheckboxcards"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/choice-checkbox-cards.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "ChoiceCheckboxCards in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/choice-checkbox-cards-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/choice-checkbox-cards-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/choice-checkbox-cards.webp"
"twitter:image:alt": "ChoiceCheckboxCards in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/choicecheckboxcards#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/choicecheckboxcards",
      "name": "ChoiceCheckboxCards",
      "description": "Multi-select card group with rich options, selection limits, and animated checkmark indicators.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/choicecheckboxcards#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/choicecheckboxcards#article",
      "url": "https://flex-fields.bjanczak.com/docs/choicecheckboxcards",
      "headline": "ChoiceCheckboxCards",
      "description": "Multi-select card group with rich options, selection limits, and animated checkmark indicators.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/choicecheckboxcards#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/choice-checkbox-cards-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/choice-checkbox-cards-motion.mp4#video",
      "name": "ChoiceCheckboxCards — FLEX Fields demonstration",
      "description": "A recorded demonstration of ChoiceCheckboxCards in the FLEX Fields playground for Filament. Multi-select card group with rich options, selection limits, and animated checkmark indicators.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/choice-checkbox-cards.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.867S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/choice-checkbox-cards-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/choicecheckboxcards#article"
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
  src="/art/previews/choice-checkbox-cards-motion.mp4"
  poster="/art/previews/choice-checkbox-cards.webp"
  width="1920"
  height="1080"
  aria-label="ChoiceCheckboxCards — FLEX Fields demonstration"
>
  <a href="/art/previews/choice-checkbox-cards-motion.mp4">Watch the ChoiceCheckboxCards demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Premium **multi-select** card group (checkbox behavior). Features rich option metadata, Material-style ripples, and built-in validation for minimum, maximum, or exact selection counts.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCheckboxCards` |
| **State type** | `array<int\|string>` — list of selected option keys |
| **Model cast** | `'tags' => 'array'` · `'features' => 'json'` |
| **FieldType** | `choice_checkbox_cards` |
| **Playground** | `choice-checkbox-cards` slug in Flex Fields playground |

---

### Basic usage

#### Feature selection (Stack layout)

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCheckboxCards;

ChoiceCheckboxCards::make('features')
    ->options([
        'security' => ['label' => 'Security', 'description' => 'Real-time threat detection'],
        'storage' => ['label' => 'Storage', 'description' => 'Unlimited cloud storage'],
        'analytics' => ['label' => 'Analytics', 'description' => 'Advanced reporting'],
    ])
    ->minSelections(1)
    ->default(['security']);
```

#### Add-ons grid (Check indicator)

```php
ChoiceCheckboxCards::make('addons')
    ->layout('grid')
    ->gridColumns(['default' => 1, 'sm' => 3])
    ->indicator('check')
    ->options([
        'backups' => ['label' => 'Backups', 'price' => '$5/mo'],
        'monitoring' => ['label' => 'Monitoring', 'price' => '$9/mo'],
        'support' => ['label' => 'Support', 'price' => '$15/mo'],
    ]);
```

---

### State & validation

#### Stored value

State is an **array** of unique option keys.

```php
$record->features; // ['security', 'analytics']
```

#### Validation rules (built-in)

| Rule | Description |
|------|-------------|
| `array` | Always enforced. |
| Option keys | Selected keys must exist in `options()`. |
| `minSelections(n)` | Minimum number of items (default 1 if `required()`). |
| `maxSelections(n)` | Maximum number; UI blocks further selection. |
| `exactSelections(n)` | Requires exactly `n` items; overrides min/max. |

---

### Configuration API

Shares the core card API with [ChoiceCards](/docs/choicecards).

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `options(array\|Closure $options)` | Setup | `[]` | Rich option map (label, description, price, icon, etc.). |
| `minSelections(int\|Closure $n)` | Validation | `null` | Minimum required selections. |
| `maxSelections(int\|Closure $n)` | Validation | `null` | Maximum allowed selections. |
| `exactSelections(int\|Closure $n)` | Validation | `null` | Requires exactly `n` selections. |
| `layout(string\|Closure $layout)` | Setup | `'stack'` | `stack`, `grid`, `media`, `featured`. |
| `gridColumns(int\|array\|Closure $cols)` | Setup | `1` | Responsive columns (max 4). Media layouts reduce the column count in narrow fields. Prices stay on the trailing side while descriptions wrap. |
| `indicator(string\|Closure $type)` | Setup | `auto` | `checkbox`, `check`, `none`. |
| `variant(string\|Closure $variant)` | Setup | `'default'` | `default`, `primary`, `secondary`. |
| `color(string\|Closure $color)` | Setup | `'primary'` | Accent color for selection. |
| `ripple(bool\|Closure $on)` | Setup | `false` | Enable Material-style click ripple. |

---

### Real-world examples

#### Pizza toppings with limits

```php
ChoiceCheckboxCards::make('toppings')
    ->label('Select up to 3 toppings')
    ->options([
        'cheese' => 'Extra Cheese',
        'pepperoni' => 'Pepperoni',
        'mushrooms' => 'Mushrooms',
        'olives' => 'Olives',
    ])
    ->minSelections(1)
    ->maxSelections(3);
```

#### Integration selector with icons

```php
ChoiceCheckboxCards::make('integrations')
    ->layout('media')
    ->gridColumns(3)
    ->indicator('none')
    ->options([
        'github' => ['label' => 'GitHub', 'icon' => 'hugeicons-github'],
        'slack' => ['label' => 'Slack', 'icon' => 'hugeicons-slack'],
        'linear' => ['label' => 'Linear', 'icon' => 'hugeicons-dashboard-square-01'],
    ]);
```

---

### Playground

`/admin/flex-fields/choice-checkbox-cards`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [ChoiceCards](/docs/choicecards) | For single-select card groups. |
| [FlexChecklist](/docs/flexchecklist) | For a more compact multi-select list. |
| [MatrixChoiceField](/docs/matrixchoicefield) | For complex grid-based multi-selection. |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-choice-cards` | Root wrapper (shared with ChoiceCards) |
| `fff-choice-card` | Individual card element |
| `fff-choice-card--selected` | Active card state |
| `fff-choice-card__indicator` | Checkbox/check marker wrapper |

Uses the same BEM structure as [ChoiceCards](/docs/choicecards).

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

`exactSelections()` takes precedence over minimum and maximum settings. The
selection cap also blocks additional choices immediately; selected choices can
always be removed. Negative limits and a minimum above the maximum are invalid
configuration. If a stored selection is now disabled, resolve it before saving;
Image Choice Cards can clear disabled choices with `pruneDisabledSelection()`.
