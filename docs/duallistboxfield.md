---
title: "DualListboxField"
description: Dual-pane selection tool with search, reordering, and virtual scrolling.
icon: "list-start"
"canonical": "https://flex-fields.bjanczak.com/docs/duallistboxfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/dual-listbox.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "DualListboxField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/dual-listbox-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/dual-listbox-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/dual-listbox.webp"
"twitter:image:alt": "DualListboxField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/duallistboxfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/duallistboxfield",
      "name": "DualListboxField",
      "description": "Dual-pane selection tool with search, reordering, and virtual scrolling.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/duallistboxfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/duallistboxfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/duallistboxfield",
      "headline": "DualListboxField",
      "description": "Dual-pane selection tool with search, reordering, and virtual scrolling.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/duallistboxfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/dual-listbox-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/dual-listbox-motion.mp4#video",
      "name": "DualListboxField — FLEX Fields demonstration",
      "description": "A recorded demonstration of DualListboxField in the FLEX Fields playground for Filament. Dual-pane selection tool with search, reordering, and virtual scrolling.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/dual-listbox.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.5S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/dual-listbox-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/duallistboxfield#article"
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
  src="/art/previews/dual-listbox-motion.mp4"
  poster="/art/previews/dual-listbox.webp"
  width="1920"
  height="1080"
  aria-label="DualListboxField — FLEX Fields demonstration"
>
  <a href="/art/previews/dual-listbox-motion.mp4">Watch the DualListboxField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

![Dual Listbox with searchable panes, shared checkmarks and transfer controls](/docs/assets/components/dual-listbox.png)

[← Back to Table of Contents](/docs/index)

### Summary

Multi-select with "Available" and "Selected" panes. Supports real-time search, drag-and-drop reordering, double-click transfer, and virtual scrolling for high-performance handling of thousands of options.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\DualListboxField` |
| **State type** | `list<string>` — ordered selected keys; integer option keys are accepted and normalized |
| **Model cast** | `'roles' => 'array'` · `'tags' => 'array'` |
| **FieldType** | `dual-listbox` |
| **Playground** | `dual-listbox` slug in Flex Fields playground |
| **Default variant** | `bordered` |

---

### Basic usage

#### Standard role selection
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\DualListboxField;

DualListboxField::make('roles')
    ->options([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ])
    ->searchable();
```

#### Locked options
Lock keys against transfer in the interface. Locked items already in Selected can still be reordered.

<Warning>Interaction locks are not authorization rules. When a particular key must remain present or must never be granted, enforce that requirement with server-side rules and your application policy.</Warning>

```php
DualListboxField::make('permissions')
    ->options($permissions)
    ->disabledOptions(['users.view', 'users.delete']);
```

#### Custom labels and height
```php
DualListboxField::make('permissions')
    ->options(['read' => 'Read records', 'write' => 'Write records'])
    ->availableLabel('Unassigned Permissions')
    ->selectedLabel('Active Permissions')
    ->listHeight('24rem')
    ->reorderable(false);
```

---

### State & validation

#### Stored value
The field stores an array of keys from the `options()` list. The order of keys matches the order in the "Selected" pane. `reorderable()` controls whether users can change that order.

```php
$record->roles; // ['admin', 'editor']
```

Use a JSON column and an Eloquent `array` cast for this attribute. The field validates and dehydrates form state; your Filament resource or application saves it to the model.

#### Validation Rules

The server rejects non-list values, nested or boolean keys, duplicates, unknown option keys and count violations. Integer keys are stored as strings. Empty selections are valid unless `required()`, `minItems()` or `exactItems()` says otherwise. Call the form’s validated `getState()` before persisting; do not save raw client properties. Apply record, tenant and permission policies in the application.

You can enforce minimum or maximum selection counts:

| Rule | Method | Description |
|------|--------|-------------|
| `min` | `minItems(5)` | Must select at least 5 items |
| `max` | `maxItems(10)` | Cannot select more than 10 items |
| `exact` | `exactItems(3)` | Must select exactly 3 items |

---

### Advanced Features

#### Virtual Scrolling
When the number of options exceeds **100**, the component automatically enables virtual scrolling. Only the visible rows and a small buffer are rendered. Search still operates on the complete supplied option list. Adjust the threshold with `virtualThreshold(100)`. Virtualization limits DOM work; it does not paginate options or remove the initial data payload. The supported option limit is 50,000.

#### Search behavior

Both panes search labels and descriptions locally. Matching ignores case and combining accents: `sao` matches `São`. Queries are limited to 256 characters. Counts show the number of matches in each pane; searching does not remove stored selections. Mark all and bulk-add actions operate on the current filtered Available list.

#### Double-click Transfer
Users can double-click an unlocked item to move it to the other pane, subject to the maximum selection count. This can be disabled via `moveOnDoubleClick(false)`.

#### Reorder selected items
Drag items in the Selected pane to change order, including locked (`disabledOptions`) rows. Those rows cannot be moved to Available. Arrow buttons remain as an accessible fallback. Disable both with `reorderable(false)`. On stacked (narrow) layouts, transfer arrows point up and down.

#### Multi-select on touch

Tap options to mark several without modifiers. On desktop, click the checkmark to toggle individual marks, use Cmd/Ctrl + click for additive selection, or Shift + click for a range.

On phones and tablets, scroll the list normally. Hold one item still for a moment to pick it up — a stacked preview follows that thumb, with a count badge when more than one item is held. Tap more items with another finger to add them to the stack. After you drop the group, the highlight is cleared. Drop on the Selected list to insert before or after a row (upper vs lower half), or on Available to remove items from Selected. Transfer buttons still move the whole selection. On desktop, hold Cmd/Ctrl (or Shift for a range) and use drag-and-drop in the Selected pane.

#### Keyboard and RTL

| Key | Action |
| --- | --- |
| Up / Down, Home / End | Focus an option, including virtual rows |
| Space | Toggle the focused option’s mark |
| Shift + Up / Down | Mark a contiguous range |
| Cmd / Ctrl + A | Mark the current filtered pane |
| Enter | Transfer marked options, or the focused option |
| Arrow toward the other pane | Transfer; horizontal direction mirrors in RTL |
| Alt + Up / Down | Reorder the selected option |
| Escape | Clear marks or cancel an active drag |

Set `dir="rtl"` on the surrounding form or container. Pane layout, action direction and logical spacing follow that direction. Narrow containers stack the panes and rotate transfer arrows vertically. Long labels and descriptions are truncated in fixed-height rows; the full text remains in the row’s title.

#### Rich options

```php
DualListboxField::make('permissions')
    ->options([
        'read' => [
            'label' => 'Read records',
            'description' => 'Browse the shared directory',
        ],
        'delete' => [
            'label' => 'Delete records',
            'disabled' => true,
        ],
    ]);
```

#### Custom Icons
Transfer, reorder and search icons can be customized to match your UI. Pane heading icons, the lock indicator and the shared Checklist checkmark are fixed.

```php
DualListboxField::make('items')
    ->icons([
        'search' => 'hugeicons-search-01',
        'move_right' => 'hugeicons-arrow-right-01',
        'move_left' => 'hugeicons-arrow-left-01',
    ]);
```

---

### Configuration API

Configuration methods below accept a `Closure`. `icons()` evaluates its map when called; use the individual icon setters for icons that must be evaluated with the field context.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `options(array\|Closure $options)` | Setup | `[]` | Key => label or rich-option array; closures must return an array |
| `disabledOptions(array\|Closure $keys)` | Setup | `[]` | Keys that cannot be moved |
| `variant(string\|Closure $variant)` | Setup | `'bordered'` | `bordered`, `flat`, `faded` |
| `listHeight(string\|Closure $h)` | Setup | `'16rem'` | Height of the list panes |
| `searchable(bool\|Closure $cond)` | Setup | `true` | Enable search inputs |
| `reorderable(bool\|Closure $cond)` | Setup | `true` | Enable drag reordering, arrow buttons and Alt + Up / Down |
| `moveOnDoubleClick(bool\|Closure $c)`| Setup | `true` | Transfer on double-click |
| `showTransferButtons(bool\|Closure $c)`| Setup | `true` | Show central action buttons |
| `availableLabel(string\|Closure\|null $l)` | Setup | (trans) | Label for Available |
| `selectedLabel(string\|Closure\|null $l)`  | Setup | (trans) | Label for Selected |
| `minItems(int\|Closure\|null $count)`      | Setup | `null` | Minimum selection count |
| `maxItems(int\|Closure\|null $count)`      | Setup | `null` | Maximum selection count |
| `exactItems(int\|Closure\|null $count)` | Setup | `null` | Set both minimum and maximum to the same count |
| `virtualThreshold(int\|Closure $count)` | Setup | `100` | Virtualize panes exceeding this count; accepts 1–50,000 |
| `readOnly(bool\|Closure $condition)` | Setup | `false` | Keep the value visible without interaction |
| `readOnlyOn(string\|array $operations)` | Setup | none | Read-only for specific form operations or Livewire component classes |
| `disabled(bool\|Closure $condition)` | Setup | `false` | Disable interaction; follows Filament disabled-field dehydration behavior |
| `size(string\|ControlSize\|Closure $size)`         | Setup | `'md'` | `sm`, `md`, `lg` (padding and list labels; search uses the sm control height) |
| `rounding(string\|Closure\|null $round)`    | Setup | config | Border radius token |

---

### Option format and localization

Option keys must be strings or integers with valid UTF-8 text, at most 512 bytes and without control characters. Labels accept up to 4,096 bytes and descriptions up to 8,192 bytes of UTF-8 text. A rich option may contain `label`, `description` and the boolean `disabled` flag; omitting `label` uses its key. Supply plain text, not HTML.

`availableLabel()` and `selectedLabel()` customize pane headings. Search placeholders, empty states, action labels and keyboard hints use the package translations. Override keys in your application’s `lang/vendor/filament-flex-fields/en/default.php` (change `en` for another locale):

```php
return [
    'dual_listbox' => [
        'search_available' => 'Find available permissions…',
        'search_selected' => 'Find granted permissions…',
        'empty_available' => 'All permissions assigned',
        'empty_selected' => 'Choose your first permission',
    ],
];
```

### Real-world examples

#### User Permissions Management

Use your application’s `Permission` model. Convert the query result to an array; `options()` does not accept a Collection.

```php
DualListboxField::make('permissions')
    ->label('System Permissions')
    ->options(fn (): array => Permission::query()
        ->orderBy('name')
        ->pluck('name', 'id')
        ->all())
    ->searchable()
    ->availableLabel('Available')
    ->selectedLabel('Granted')
    ->listHeight('30rem')
    ->required();
```

---

#### Exactly three choices

```php
DualListboxField::make('featured_categories')
    ->options([
        'news' => 'News',
        'events' => 'Events',
        'guides' => 'Guides',
        'reviews' => 'Reviews',
    ])
    ->exactItems(3)
    ->listHeight('20rem')
    ->virtualThreshold(100);
```

#### Minimal and read-only interfaces

```php
DualListboxField::make('categories')
    ->options(['news' => 'News', 'events' => 'Events'])
    ->searchable(false)
    ->showTransferButtons(false)
    ->moveOnDoubleClick()
    ->reorderable(false);

DualListboxField::make('approved_categories')
    ->options(['news' => 'News', 'events' => 'Events'])
    ->readOnly();
```

A read-only field keeps its value available for normal form submission. A disabled field follows Filament’s dehydration policy and is excluded by default unless explicitly configured otherwise. Both prevent interaction.

#### Sizes and variants

```php
DualListboxField::make('compact_categories')
    ->options(['news' => 'News', 'events' => 'Events'])
    ->size('sm')
    ->variant('flat');

DualListboxField::make('spacious_categories')
    ->options(['news' => 'News', 'events' => 'Events'])
    ->size('lg')
    ->variant('faded')
    ->rounding('lg');
```

The `bordered` variant name is retained for compatibility; its current workspace and option cards do not add outlines. The three variants change the workspace surface. Search follows the shared Flex Text Input presentation independently.

When `options()` depends on another field, its closure is reevaluated on the server. Reset or replace the selected value when the permitted options change; outdated keys fail validation rather than being silently accepted.

### Playground

`/admin/flex-fields/dual-listbox` when the panel uses the `/admin` prefix.

The playground includes descriptions, interaction locks, three sizes, visual variants, read-only and disabled fields, selection limits and a 1,000-option virtual list.

See [Installation](/docs/installation) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [FlexChecklist](/docs/flexchecklist) | Simple multi-select for small lists |
| [SelectField](/docs/selectfield) | Standard dropdown with multi-select support |
| [ChoiceCards](/docs/choicecards) | Visual card-based multi-selection |

---

### Appearance and icon configuration

The workspace has a 24 px outer radius. Its inner panels derive their radius from that value minus the 8 px workspace inset (16 px), so nested corners remain aligned. Scroll edges use fixed gradients that fade in and out; reaching the start or end removes the corresponding fade. Search reuses Flex Text Input’s shell and control with its shadow-free `primary` variant and whole-control focus outline. Option cards remain visible before hover. Checkmarks share the same shape and animation as Flex Checklist. Both controls use Hugeicons by default and respect reduced-motion preferences.

`icons()` accepts these keys: `search`, `move_all_right`, `move_right`, `swap`, `move_left`, `move_all_left`, `move_up`, `move_down`. Individual setters are `searchIcon()`, `moveAllRightIcon()`, `moveRightIcon()`, `swapIcon()`, `moveLeftIcon()`, `moveAllLeftIcon()`, `moveUpIcon()` and `moveDownIcon()`; each accepts a registered icon name, enum, HTML icon or closure.

`listHeight()` accepts a positive simple length in `px`, `rem`, `em`, `vh` or `dvh`. Count settings accept 0–50,000 or `null`; the minimum cannot exceed the maximum. `rounding()` configures the shared rounding token, including search controls; it does not change the workspace’s outer 24 px radius.

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-dual-listbox-field` | Root wrapper |
| `fff-dual-listbox__panel` | Individual list container |
| `fff-dual-listbox__search` | Search input wrapper |
| `fff-dual-listbox__list` | The actual scrollable list |
| `fff-dual-listbox__viewport` | Owns the non-interactive fading scroll edges |
| `fff-dual-listbox__item` | Individual option element |
| `fff-dual-listbox__toolbar` | Central button group |
| `fff-dual-listbox-field--{variant}` | Visual variant class |
