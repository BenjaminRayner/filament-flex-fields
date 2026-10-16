---
title: "FlexChecklist"
description: "Filament checkbox lists with animated indicators, icons, descriptions, keyboard navigation and validated selection limits."
icon: "clipboard-check"
"canonical": "https://flex-fields.bjanczak.com/docs/flexchecklist"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/flex-checklist.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "FlexChecklist in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/flex-checklist-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/flex-checklist-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/flex-checklist.webp"
"twitter:image:alt": "FlexChecklist in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/flexchecklist#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/flexchecklist",
      "name": "FlexChecklist",
      "description": "Filament checkbox lists with animated indicators, icons, descriptions, keyboard navigation and validated selection limits.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexchecklist#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/flexchecklist#article",
      "url": "https://flex-fields.bjanczak.com/docs/flexchecklist",
      "headline": "FlexChecklist",
      "description": "Filament checkbox lists with animated indicators, icons, descriptions, keyboard navigation and validated selection limits.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexchecklist#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/flex-checklist-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/flex-checklist-motion.mp4#video",
      "name": "FlexChecklist — FLEX Fields demonstration",
      "description": "A recorded demonstration of FlexChecklist in the FLEX Fields playground for Filament. Filament checkbox lists with animated indicators, icons, descriptions, keyboard navigation and validated selection limits.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/flex-checklist.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.617S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/flex-checklist-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexchecklist#article"
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
  src="/art/previews/flex-checklist-motion.mp4"
  poster="/art/previews/flex-checklist.webp"
  width="1920"
  height="1080"
  aria-label="FlexChecklist — FLEX Fields demonstration"
>
  <a href="/art/previews/flex-checklist-motion.mp4">Watch the FlexChecklist demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Choose several options in a compact list. Each row can include an icon, description and an animated checkbox. Use [FlexRadiolist](/docs/flexradiolist) when only one choice is allowed.

## Basic checklist

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexChecklist;

FlexChecklist::make('features')
    ->label('Included features')
    ->options([
        'wifi' => 'Wi-Fi',
        'parking' => 'Parking',
        'breakfast' => 'Breakfast',
    ])
    ->default(['wifi']);
```

The state is a list of selected keys, such as `['wifi', 'breakfast']`. Numeric keys are accepted and normalized to strings when the field saves its state. An empty selection is `[]`.

## Icons and descriptions

<Tabs>
<Tab title="Rich options">

```php
FlexChecklist::make('permissions')
    ->options([
        'read' => [
            'label' => 'View records',
            'description' => 'Read-only access to project records',
            'icon' => 'hugeicons-view',
        ],
        'write' => [
            'label' => 'Edit records',
            'desc' => 'Update existing project records',
            'icon' => 'hugeicons-pencil-edit-01',
        ],
    ]);
```

`desc` is an alias for `description`. Explicit option icons and descriptions take precedence over the separate helper arrays.

</Tab>
<Tab title="Separate helper arrays">

```php
FlexChecklist::make('files')
    ->options([
        'documents' => 'Documents',
        'reports' => 'Reports',
    ])
    ->icons([
        'documents' => 'hugeicons-folder-01',
        'reports' => 'hugeicons-file-01',
    ])
    ->descriptions([
        'documents' => 'Shared project folder',
        'reports' => 'Monthly reports',
    ]);
```

Icons and descriptions are optional. Omit them for a plain text list.

</Tab>
</Tabs>

## Selection limits

```php
FlexChecklist::make('services')
    ->options([
        'cleaning' => 'Cleaning',
        'catering' => 'Catering',
        'transport' => 'Transport',
    ])
    ->required()
    ->minSelections(1)
    ->maxSelections(2);
```

At the maximum, unchecked options become unavailable immediately. Selected options remain available so users can deselect them. Minimum counts are checked when the form is validated; users can temporarily clear a selection while editing.

```php
FlexChecklist::make('finalists')
    ->options($candidates)
    ->exactSelections(3);
```

`exactSelections()` takes precedence over minimum and maximum. It also caps additions in the interface. Limits accept nonnegative integers, closures or `null`. `null` removes that limit. A required checklist has an effective minimum of one unless a minimum is explicitly set. Negative counts and a minimum greater than the maximum, without an exact limit, are invalid configurations.

## Unavailable options

```php
FlexChecklist::make('services')
    ->options([
        'standard' => 'Standard service',
        'priority' => 'Priority service',
        'retired' => [
            'label' => 'Retired service',
            'description' => 'No longer available',
            'disabled' => true,
        ],
    ])
    ->disabledOptions(['priority']);
```

Disabled options display a lock, cannot be selected or deselected by the user, and are excluded from accepted submissions. If an existing record contains a key that is now disabled, resolve that stored selection or re-enable the option before saving. Disabling the entire field follows Filament's normal disabled-field behavior.

## Sizes and colors

```php
use Bjanczak\FilamentFlexFields\Enums\ControlSize;

FlexChecklist::make('features')
    ->options($features)
    ->size(ControlSize::Lg)
    ->color('success');
```

Sizes are `sm`, `md` and `lg` (default). Each also accepts the corresponding `ControlSize` enum. The default accent is the panel's primary color. Standard panel colors include `primary`, `secondary`, `success`, `warning` and `danger`. `color(null)` omits the color class. 

## Dynamic options and dependent forms

```php
use Filament\Schemas\Components\Utilities\Get;

FlexChecklist::make('services')
    ->options(fn (Get $get): array => match ($get('package')) {
        'premium' => ['cleaning' => 'Cleaning', 'catering' => 'Catering'],
        default => ['cleaning' => 'Cleaning'],
    })
    ->live();
```

Options, icons, descriptions, disabled keys, size, color and limits accept closures evaluated by Filament. A parent field must be `live()` when its change should rebuild dependent options. Clear dependent selections when their available keys change; unavailable old values fail server validation rather than being silently accepted. Filament's `afterStateUpdated()` and standard field configuration remain available.

## Saving to a model

Use a JSON column and an array cast for multiple choices:

```php
protected function casts(): array
{
    return [
        'features' => 'array',
    ];
}
```

Save through the validated Filament form state. The built-in rules reject non-list arrays, nested values, booleans, floats, duplicates, unknown keys, disabled options and invalid counts. Malformed state is retained until validation so it cannot disappear during casting and accidentally pass validation. Options supplied by a database query must still be scoped to the current user's permissions.

## Keyboard and mobile

- Tab enters the list at its active option.
- Up/Down and Home/End move focus without changing checkbox selections.
- Left/Right also move focus and respect RTL direction.
- Space or Enter toggles the focused option once per press.
- Disabled options and unchecked options blocked by the limit are skipped.

The whole row responds to a click or tap. Text wraps at narrower widths. Reduced-motion preferences suppress indicator animation. Each list has independent state and event ownership, including when several lists use the same option keys.

Direction is inherited from the surrounding page. To set RTL for one field:

```php
FlexChecklist::make('selection')
    ->options($options)
    ->extraAttributes(['dir' => 'rtl']);
```

## Configuration reference

| Method | Default | Purpose |
|---|---|---|
| `options(array\|Closure)` | `[]` | Keyed labels or rich option arrays. |
| `icons(array\|Closure)` | `[]` | Per-key icons. |
| `descriptions(array\|Closure)` | `[]` | Per-key supporting text. |
| `disabledOptions(array\|Closure)` | `[]` | Keys unavailable for selection and submission. |
| `minSelections(int\|Closure\|null)` | `null` | Minimum selected count. |
| `maxSelections(int\|Closure\|null)` | `null` | Maximum selected count and interface cap. |
| `exactSelections(int\|Closure\|null)` | `null` | Exact count; overrides min/max. |
| `size(string\|ControlSize\|Closure)` | `md` | Row, text, icon and indicator size. |
| `color(string\|Closure\|null)` | `primary` | Panel accent color. |

Rich option keys are `label`, `description` (or `desc`), `icon` and `disabled`. Labels and descriptions render as escaped text. In JSON field definitions, the field type is `flex_checklist`.
