---
title: "BubbleChoiceField"
description: "An interactive bubble multi-select for Filament, with smooth panning, optional image choices, animated selection and server-side validation."
icon: "bubbles"
"canonical": "https://flex-fields.bjanczak.com/docs/bubblechoicefield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/bubble-choice.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "BubbleChoiceField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/bubble-choice-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/bubble-choice-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/bubble-choice.webp"
"twitter:image:alt": "BubbleChoiceField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/bubblechoicefield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/bubblechoicefield",
      "name": "BubbleChoiceField",
      "description": "An interactive bubble multi-select for Filament, with smooth panning, optional image choices, animated selection and server-side validation.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/bubblechoicefield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/bubblechoicefield#article",
      "url": "https://flex-fields.bjanczak.com/docs/bubblechoicefield",
      "headline": "BubbleChoiceField",
      "description": "An interactive bubble multi-select for Filament, with smooth panning, optional image choices, animated selection and server-side validation.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/bubblechoicefield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/bubble-choice-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/bubble-choice-motion.mp4#video",
      "name": "BubbleChoiceField — FLEX Fields demonstration",
      "description": "A recorded demonstration of BubbleChoiceField in the FLEX Fields playground for Filament. An interactive bubble multi-select for Filament, with smooth panning, optional image choices, animated selection and server-side validation.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/bubble-choice.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT4.683S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/bubble-choice-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/bubblechoicefield#article"
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
  src="/art/previews/bubble-choice-motion.mp4"
  poster="/art/previews/bubble-choice.webp"
  width="1920"
  height="1080"
  aria-label="BubbleChoiceField — FLEX Fields demonstration"
>
  <a href="/art/previews/bubble-choice-motion.mp4">Watch the BubbleChoiceField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Bubble Choice displays multiple choices in a pannable arena. Bubbles near the center stay large; those near the edge shrink. Choose plain labels, descriptions, photo backgrounds or small image icons. Selected bubbles can stay circular, grow, or morph into a scalloped shape.

| | |
|---|---|
| Class | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\BubbleChoiceField` |
| Saved value | An ordered list of string option keys |
| Model cast | `'habits' => 'array'` |
| Schema type | `bubble_choice` |
| Live example | [Bubble Choice](https://demo.bjanczak.com/admin/flex-fields/bubble-choice) |

## First field

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BubbleChoiceField;

BubbleChoiceField::make('habits')
    ->label('Choose your habits')
    ->options([
        'water' => 'Drink water',
        'read' => 'Read',
        'walk' => 'Walk',
        'journal' => 'Keep a journal',
    ])
    ->arenaHeight('400px')
    ->maxItems(3);
```

The stored value might be `['walk', 'read']`. Numeric option keys are saved as strings; `0` is a supported key. Selection order is preserved. The component works with an ordinary JSON column and an Eloquent array cast:

```php
protected function casts(): array
{
    return [
        'habits' => 'array',
    ];
}
```

## Rich choices

Labels and descriptions are plain text. Image URLs are configured on the server; they are not uploaded by this field.

```php
BubbleChoiceField::make('destinations')
    ->options([
        'forest' => [
            'label' => 'Forest',
            'description' => 'A quiet retreat',
            'image' => asset('images/forest.jpg'),
            'imageMode' => 'background',
        ],
        'coast' => [
            'label' => 'Coast',
            'description' => 'By the sea',
            'image' => asset('images/coast.jpg'),
            'imageMode' => 'icon',
            'color' => '#1e3a8a',
            'selectedColor' => '#93c5fd',
        ],
    ])
    ->required();
```

| Option property | Default | Purpose |
|---|---|---|
| `label` | Option key | Main text |
| `description` | `null` | Secondary line; `desc` is also accepted |
| `image` | `null` | Image URL |
| `imageMode` | `background` | Full bubble photograph or centered `icon`; `image_mode` is also accepted |
| `color` | Field/theme color | Idle fill for this choice |
| `selectedColor` | Field/theme selected color | Selected fill or photo outline; `selected_color` is also accepted |
| `disabled` | `false` | Prevents selecting and submitting this option |

## Colors and appearance

```php
BubbleChoiceField::make('habits')
    ->options([
        'walk' => 'Walk',
        'read' => 'Read',
        'water' => 'Drink water',
    ])
    ->variant('soft')
    ->selectedShape('scallop')
    ->arenaColor('#16260a')
    ->bubbleColor('#8cb76c')
    ->selectedBubbleColor('#c8f560')
    ->size('md');
```

| Method | Default | Available configuration |
|---|---|---|
| `variant()` | `soft` | `soft`, `solid`, `outline` |
| `selectedShape()` | `scallop` | `scallop`, `circle`, `grow` |
| `arenaHeight()` | `500px` | A valid CSS height, such as `400px` or `28rem` |
| `arenaColor()` | Theme default | Arena background; `null` restores the theme |
| `bubbleColor()` | Theme default | Idle bubble color; options can override it |
| `selectedBubbleColor()` | Theme default | Selected color; options can override it |
| `size()` | `md` | Shared `sm`, `md`, `lg` field size token; use `bubbleSize()` for bubble diameter |
| `rounding()` | Shared field default | Field wrapper rounding token; the arena retains its 24px design radius |
| `disabled()` | `false` | Disables interaction; normal Filament disabled-state dehydration applies |
| `readOnly()` | `false` | Shows the value without allowing changes through the UI |

Light mode uses a pale gray arena; dark mode uses charcoal. Built-in bubble colors follow the panel's primary palette. Explicit field/option colors remain the configured colors in both themes. Hex and RGB custom colors receive a calculated foreground; image backgrounds use white text. Choose adequate contrast for other CSS color formats.

## Layout

```php
BubbleChoiceField::make('activities')
    ->options([
        'read' => 'Read',
        'walk' => 'Walk',
        'cycle' => 'Cycle',
        'swim' => 'Swim',
    ])
    ->layoutOptions([
        'size' => 160,
        'minSize' => 25,
        'gutter' => 8,
        'numCols' => 6,
        'fringeWidth' => 160,
        'yRadius' => 130,
        'xRadius' => 220,
        'cornerRadius' => 50,
        'compact' => true,
        'gravitation' => 5,
        'provideProps' => true,
    ]);
```

| Layout key | Individual method | Default | Meaning |
|---|---|---|---|
| `size` | `bubbleSize()` | `160` | Maximum bubble diameter in pixels |
| `minSize` | `minSize()` | `25` | Diameter at the outer fringe |
| `gutter` | `gutter()` | `8` | Gap between cells |
| `numCols` | `numCols()` | `6` | Columns in staggered packing; capped to the option count |
| `fringeWidth` | `fringeWidth()` | `160` | Width of the shrinking zone; zero gives an immediate boundary |
| `yRadius` | `yRadius()` | `130` | Half-height of the full-size center |
| `xRadius` | `xRadius()` | `220` | Half-width of the full-size center |
| `cornerRadius` | `cornerRadius()` | `50` | Corner radius of that center, capped to its x/y radii |
| `compact` | `compact()` | `true` | Pull shrinking bubbles inward |
| `gravitation` | `gravitation()` | `5` | Additional pull outside the fringe |
| `provideProps` | `provideProps()` | `true` | Exposes `--fff-bubble-runtime-size` in pixels on each cell |

Numbers must be finite and nonnegative. Bubble diameter must be at least 1px; minimum diameter cannot exceed it. Numeric layout values are bounded at 10,000 and column count at 4,096. Boolean options accept actual booleans. Unknown keys and inconsistent values raise a configuration error. A later individual setter overrides the corresponding `layoutOptions()` value; a later `layoutOptions()` call replaces the previous layout overrides.

The arena starts centered. It supports touch panning and two-axis trackpad scrolling. Resizing adjusts the layout without creating another set of bubbles. Labels hide when the displayed bubble is 50px or smaller; the accessible button name remains available.

## Selection limits and validation

```php
BubbleChoiceField::make('activities')
    ->options([
        'walk' => 'Walk',
        'read' => 'Read',
        'swim' => 'Swim',
        'blocked' => ['label' => 'Unavailable', 'disabled' => true],
    ])
    ->disabledOptions(['swim'])
    ->required()
    ->minItems(1)
    ->maxItems(2);
```

| Method | Effect |
|---|---|
| `required()` | Requires at least one selected option |
| `minItems(n)` | Requires at least `n` choices |
| `maxItems(n)` | Allows at most `n`; prevents adding another choice in the UI |
| `exactItems(n)` | Requires exactly `n` at submission |
| `disabledOptions([...])` | Disables the listed configured keys |

Limits can be `null` to remove them or integers from 0 to 4,096. `maxItems(0)` prevents all additions; `exactItems(0)` accepts only an empty list. Combining `required()` with an exact zero cannot produce a valid selection. Contradictory minimum/maximum/exact limits raise a configuration error.

Validation rejects unknown or disabled keys, duplicates, nested values, associative arrays, booleans, floats and malformed/oversized keys. Submitted values are not silently filtered to make them valid. Use the normal validated Filament form state when saving.

<Note>
`normalizeState()` is a presentation helper that removes unknown, disabled and repeated keys. It is not a replacement for submission validation. `readOnly()` is an interaction setting, not record authorization: enforce permissions and immutable-value policies on the server.
</Note>

## Dynamic options

Option, disabled-option and layout closures support the normal Filament utility injection. Option closures are resolved against current server state rather than a stale field cache. Use `live()` on the controlling field to refresh dependent choices, and reset their state when the parent changes.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

SelectField::make('category')
    ->options(['outdoor' => 'Outdoor', 'indoor' => 'Indoor'])
    ->live()
    ->afterStateUpdated(fn (Set $set) => $set('activities', []));

BubbleChoiceField::make('activities')
    ->options(fn (Get $get): array => $get('category') === 'outdoor'
        ? ['walk' => 'Walk', 'cycle' => 'Cycle']
        : ['read' => 'Read', 'journal' => 'Journal'])
    ->maxItems(2);
```

Collections are bounded at 4,096 configured options. Keys must be nonempty valid UTF-8 strings up to 512 bytes, without control characters. Labels and other configured option strings are bounded at 8,192 bytes. This is an interactive visual picker, not a paginated remote search field; use [SelectField](/docs/selectfield) for that workflow.

## Keyboard and motion

- Tab focuses an available bubble. Enter or Space selects it.
- Arrow keys move to a neighboring available choice; left/right respect RTL direction.
- Home and End move to the first or last available choice.
- Keyboard movement brings the target to the center without changing the selected value.
- After initialization, the centered bubbles fade in with a subtle scale transition. The arena keeps its reserved height throughout.
- Reduced-motion preferences disable the entrance animation, shape tweening and decorative transitions.

Component CSS and JS use FLEX's per-instance asset loading. Inactive tabs and closed modal content initialize on activation. Removing a field disposes its listeners, observer, pending animation frame and state subscriptions.

## Related components

<CardGroup cols={2}>
  <Card title="Choice checkbox cards" icon="square-check" href="/docs/choicecheckboxcards">Structured choices in a card grid.</Card>
  <Card title="Image choice cards" icon="images" href="/docs/imagechoicecards">A fixed image-forward selection grid.</Card>
</CardGroup>
