---
title: "FlexRadiolist"
description: "Filament single-choice radio lists with animated indicators, icons, descriptions, compact labels and accessible keyboard navigation."
icon: "circle-dot"
"canonical": "https://flex-fields.bjanczak.com/docs/flexradiolist"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/flex-radiolist.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "FlexRadiolist in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/flex-radiolist-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/flex-radiolist-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/flex-radiolist.webp"
"twitter:image:alt": "FlexRadiolist in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/flexradiolist#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/flexradiolist",
      "name": "FlexRadiolist",
      "description": "Filament single-choice radio lists with animated indicators, icons, descriptions, compact labels and accessible keyboard navigation.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexradiolist#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/flexradiolist#article",
      "url": "https://flex-fields.bjanczak.com/docs/flexradiolist",
      "headline": "FlexRadiolist",
      "description": "Filament single-choice radio lists with animated indicators, icons, descriptions, compact labels and accessible keyboard navigation.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexradiolist#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/flex-radiolist-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/flex-radiolist-motion.mp4#video",
      "name": "FlexRadiolist — FLEX Fields demonstration",
      "description": "A recorded demonstration of FlexRadiolist in the FLEX Fields playground for Filament. Filament single-choice radio lists with animated indicators, icons, descriptions, compact labels and accessible keyboard navigation.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/flex-radiolist.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.1S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/flex-radiolist-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexradiolist#article"
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
  src="/art/previews/flex-radiolist-motion.mp4"
  poster="/art/previews/flex-radiolist.webp"
  width="1920"
  height="1080"
  aria-label="FlexRadiolist — FLEX Fields demonstration"
>
  <a href="/art/previews/flex-radiolist-motion.mp4">Watch the FlexRadiolist demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Choose one option in a list of full-width rows. Add icons and descriptions, or use compact labels. For multiple choices, use [FlexChecklist](/docs/flexchecklist).

## Basic radio list

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexRadiolist;

FlexRadiolist::make('delivery')
    ->label('Delivery method')
    ->options([
        'standard' => 'Standard delivery',
        'express' => 'Express delivery',
    ])
    ->default('standard')
    ->required();
```

The state is one option key, such as `'express'`, or `null` before selection. String and integer keys are supported; numeric identities travel as strings to preserve large IDs and leading zeros. Selecting another row replaces the previous choice; clicking the selected row keeps it selected.

## Add supporting information

```php
FlexRadiolist::make('delivery')
    ->options([
        'standard' => [
            'label' => 'Standard',
            'description' => '4–10 business days',
            'icon' => 'hugeicons-shopping-cart-01',
        ],
        'express' => [
            'label' => 'Express',
            'desc' => '2–5 business days',
            'icon' => 'hugeicons-flash',
        ],
    ]);
```

`desc` is an alias for `description`. Explicit values inside an option override values supplied by `icons()` and `descriptions()`.

```php
FlexRadiolist::make('delivery')
    ->options(['standard' => 'Standard', 'express' => 'Express'])
    ->icons([
        'standard' => 'hugeicons-shopping-cart-01',
        'express' => 'hugeicons-flash',
    ])
    ->descriptions([
        'standard' => '4–10 business days',
        'express' => '2–5 business days',
    ]);
```

Neither icons nor descriptions are required. Labels and descriptions render as escaped text.

## Compact label-only variant

```php
FlexRadiolist::make('file')
    ->variant('label-only')
    ->options([
        'proposal' => 'Project proposal.pdf',
        'report' => 'Financial report.xlsx',
        'guidelines' => 'Brand guidelines.fig',
    ]);
```

`label-only` keeps the radio indicator and label and omits icons and descriptions, even if those are configured. The default variant is `default`. Other variant names are rejected.

## Unavailable choices

```php
FlexRadiolist::make('delivery')
    ->options([
        'standard' => 'Standard',
        'express' => 'Express',
        'retired' => [
            'label' => 'Retired service',
            'disabled' => true,
        ],
    ])
    ->disabledOptions(['express']);
```

Unavailable choices show a lock, cannot be selected and are excluded from accepted submissions. If a stored value is now disabled, resolve it or re-enable that option before saving. The interface skips disabled options when navigating with a keyboard.

```php
FlexRadiolist::make('delivery')
    ->options($deliveryMethods)
    ->disabled();
```

Disabling the entire field follows Filament's normal disabled-field behavior.

## Sizes and accents

```php
use Bjanczak\FilamentFlexFields\Enums\ControlSize;

FlexRadiolist::make('delivery')
    ->options($deliveryMethods)
    ->size(ControlSize::Sm)
    ->color('success');
```

Choose `sm`, `md` (default) or `lg`, or the matching `ControlSize` enum. All variants support these sizes. The default accent uses the panel primary color. Standard names include `primary`, `secondary`, `success`, `warning` and `danger`. Pass `null` to omit the color class.

## Dynamic options

```php
use Filament\Schemas\Components\Utilities\Get;

FlexRadiolist::make('delivery')
    ->options(fn (Get $get): array => $get('collection')
        ? ['pickup' => 'Collect in person']
        : ['standard' => 'Standard delivery', 'express' => 'Express delivery'])
    ->live();
```

Options, icons, descriptions, disabled keys, variant, size and color accept closures evaluated by Filament. Make the parent field `live()` when its updates should rebuild dependent options. Reset a dependent selection when the old key is no longer available. Unknown keys fail validation; they are not silently accepted. Standard Filament field hooks, including `afterStateUpdated()`, remain available.

## Saving and validation

```php
protected function casts(): array
{
    return [
        'delivery' => 'string',
    ];
}
```

Use a string column for textual keys or an integer column/cast for numeric IDs. Save validated Filament form state. The field accepts only one enabled configured key. Arrays, objects, booleans, floats, unknown keys and disabled choices are rejected. Add `required()` when an empty selection is not permitted. Scope database-backed options to the current user's permissions.

## Keyboard, RTL and touch

- Tab focuses the selected enabled radio, or the first enabled radio.
- Up/Down select the previous/next enabled option and wrap at the ends.
- Left/Right select according to the text direction, including RTL.
- Home/End select the first/last enabled option.
- Space or Enter selects the focused option once per press.

The entire row responds to click and touch. Long text wraps on small screens. Reduced-motion preferences suppress indicator motion. Repeated lists maintain independent selection and focus.

The circular indicator is rendered in its saved state immediately. Reloading the
page does not replay a selection animation; the spring plays when the selection
changes after initialization.

Direction is inherited from the surrounding page. To set RTL for one field:

```php
FlexRadiolist::make('selection')
    ->options($options)
    ->extraAttributes(['dir' => 'rtl']);
```

## Configuration reference

| Method | Default | Purpose |
|---|---|---|
| `options(array\|Closure)` | `[]` | Keyed labels or rich option arrays. |
| `icons(array\|Closure)` | `[]` | Icons indexed by option key. |
| `descriptions(array\|Closure)` | `[]` | Supporting text indexed by key. |
| `disabledOptions(array\|Closure)` | `[]` | Unavailable choice keys. |
| `variant(string\|Closure)` | `default` | `default` or `label-only`. |
| `size(string\|ControlSize\|Closure)` | `md` | `sm`, `md` or `lg`. |
| `color(string\|Closure\|null)` | `primary` | Panel accent color. |

Rich options accept `label`, `description` (or `desc`), `icon` and `disabled`. In JSON field definitions, use `flex_radiolist` as the field type.
