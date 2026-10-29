---
title: "SelectField"
description: Filament v5 Select with virtualized lists, async Livewire search, rich option rows, multi-select chips, create-option flows, and a mobile bottom sheet — full native Select API.
icon: "list-filter"
"canonical": "https://flex-fields.bjanczak.com/docs/selectfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/select-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "SelectField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/select-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/select-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/select-field.webp"
"twitter:image:alt": "SelectField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/selectfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/selectfield",
      "name": "SelectField",
      "description": "Filament v5 Select with virtualized lists, async Livewire search, rich option rows, multi-select chips, create-option flows, and a mobile bottom sheet — full native Select API.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/selectfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/selectfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/selectfield",
      "headline": "SelectField",
      "description": "Filament v5 Select with virtualized lists, async Livewire search, rich option rows, multi-select chips, create-option flows, and a mobile bottom sheet — full native Select API.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/selectfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/select-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/select-field-motion.mp4#video",
      "name": "SelectField — FLEX Fields demonstration",
      "description": "A recorded demonstration of SelectField in the FLEX Fields playground for Filament. Filament v5 Select with virtualized lists, async Livewire search, rich option rows, multi-select chips, create-option flows, and a mobile bottom sheet — full native Select API.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/select-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT7.683S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/select-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/selectfield#article"
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
  src="/art/previews/select-field-motion.mp4"
  poster="/art/previews/select-field.webp"
  width="1920"
  height="1080"
  aria-label="SelectField — FLEX Fields demonstration"
>
  <a href="/art/previews/select-field-motion.mp4">Watch the SelectField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Select one value or build a searchable multiselect with chips, checklists, images and descriptions. Large local catalogs use **virtualized lists**; remote catalogs can load **one page at a time**. On small screens, the dropdown becomes a **bottom sheet**.

<CardGroup cols={3}>
  <Card title="Choose the presentation" icon="palette" href="#configuration-api">Sizes, surfaces, rich rows, grids and custom views.</Card>
  <Card title="Work with large catalogs" icon="layers" href="#large-catalogs-and-virtualization">Local virtualization or paginated server search.</Card>
  <Card title="Save valid selections" icon="shield-check" href="#validation-rules">Allowed values, limits, relationships and database casts.</Card>
</CardGroup>

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField` |
| **Extends** | `Filament\Forms\Components\Select` |
| **State type** | `string\|int\|null` (single) · `array` (multiple) |
| **Model cast** | Optional integer cast for numeric keys · `'tags' => 'array'` for a JSON multiselect column |
| **FieldType** | `select` (use `multiple` in config for multi-select) |
| **Playground** | `select-field` slug in Flex Fields playground |

Works with all standard Filament field APIs: `required()`, `disabled()`, `hidden()`, `live()`, `afterStateUpdated()`, validation rules, etc.

This guide covers the FLEX-specific configuration methods and practical Select workflows. Shared form, schema and action methods remain available through inheritance; see the [Filament Select reference](https://filamentphp.com/docs/5.x/forms/select) for the wider inherited API.

---

### Basic usage

#### Standard select with rich options

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('framework')
    ->label('Framework')
    ->options([
        'laravel' => [
            'label' => 'Laravel',
            'description' => 'The PHP Framework for Web Artisans',
            'icon' => 'hugeicons-flash',
            'badge' => 'Framework',
        ],
        'livewire' => [
            'label' => 'Livewire',
            'description' => 'Full-stack framework for Laravel',
            'icon' => 'hugeicons-sparkles',
        ],
    ])
    ->searchable()
    ->variant('bordered')
    ->size('md');
```

#### Multi-select with chips

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('tags')
    ->multiple()
    ->options([
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
    ->chipColor('primary')
    ->required();
```

By default, multi-select **removes** chosen values from the dropdown list (pick-from-remaining / chip mode).

#### Email recipients (two-line options + chip labels)

Use `chipLabel` (or `chip_label`) on rich options when the dropdown should show name + email, but selected chips should show only the email:

```php
SelectField::make('recipients')
    ->multiple()
    ->searchable()
    ->keepSelectedOptionsInDropdown()
    ->richOptions()
    ->options([
        'jane' => [
            'label' => 'Jane Cooper',
            'description' => 'jane.cooper@example.com',
            'chipLabel' => 'jane.cooper@example.com',
        ],
        'john' => [
            'label' => 'John Smith',
            'description' => 'john.smith@example.com',
            'chipLabel' => 'john.smith@example.com',
        ],
    ]);
```

The dropdown row renders the full two-line layout; selected chips use the compact `chipLabel` text.

#### Custom value (`optionView()`)

Use `optionView()` when you need full control over option HTML — custom Blade per row and trigger (server-side equivalent of render props). Pair with `optionTriggerView()` when the closed trigger should differ from the dropdown row.

```php
SelectField::make('user_id')
    ->label('User')
    ->searchable()
    ->options([
        'fred' => [
            'label' => 'Fred',
            'description' => 'fred@example.com',
        ],
        'bob' => [
            'label' => 'Bob',
            'description' => 'bob@example.com',
        ],
    ])
    ->optionView('my-app.select.user-option')
    ->optionTriggerView('my-app.select.user-trigger') // optional
    ->optionViewData(['showEmail' => true]);
```

Each view receives `$option`, `$layout` (`list`, `trigger`, `grid`, `chip`), `$field`, `$value`, `$label`, `$description`, and any data from `optionViewData()`.

Here the custom views can read `$showEmail`. Pass a closure to `optionViewData()` when that extra data depends on the current record or form. Your custom view is responsible for displaying its own markup; keep application text escaped with Blade's `{{ }}` output.

Use `richListTriggerDisplay()` to keep the full list row (avatar + name + email) in the closed trigger instead of the compact trigger layout.

```php
SelectField::make('assignee_id')
    ->optionView('my-app.select.user-option')
    ->richListTriggerDisplay();
```

Closure form — return HTML, `Htmlable`, `View`, or a nested view name:

```php
SelectField::make('user_id')
    ->optionView(fn (array $option, string $layout): string => view('my-app.select.user-option', [
        'option' => $option,
        'layout' => $layout,
    ])->render());
```

`optionView()` automatically enables `allowHtml()` and sanitizes output through the package HTML sanitizer.

#### Rich relationship options

Use `getRichOptionFromRecordUsing()` to return a rich option array for an Eloquent relationship. FLEX renders the dropdown, search results and saved selection without application calls to `transformRichOptionsForJs()`:

```php
SelectField::make('customer_id')
    ->relationship('customer', 'name')
    ->searchable(['code', 'name'])
    ->getRichOptionFromRecordUsing(fn (Customer $record): array => [
        'label' => $record->code,
        'description' => $record->name,
    ])
    ->richListTriggerDisplay()
    ->optionDescriptionPosition('trailing');
```

The callback accepts the same rich shape as `options()`, including icons, images, badges and `chip_label`. It can be configured before or after `relationship()`. Default selected labels remain compact; `richListTriggerDisplay()` preserves the full list row. Text remains escaped unless you explicitly enable `allowHtml()`.

This is an alternative to the string-returning `getOptionLabelFromRecordUsing()` callback. Relationship query constraints, validation and persistence remain owned by Filament and the application. Existing `getOptionLabelUsing()` callbacks still return strings.

#### Multi-select checklist (keep options visible)

Use `keepSelectedOptionsInDropdown()` when selected options should stay in the list with checkmarks (dropdown stays open while toggling):

```php
SelectField::make('states')
    ->multiple()
    ->searchable()
    ->keepSelectedOptionsInDropdown()
    ->options([
        'california' => 'California',
        'texas' => 'Texas',
        'delaware' => 'Delaware',
    ]);
```

On **single**-select fields this setting has no effect.

---

### State & validation

#### Stored value

State is the **option key** from `options()`.

```php
// Single select
$record->category_id; // int|string|null — e.g. 1

// Multi-select
$record->tags; // array — e.g. ['draft', 'published']
```

#### Default state

The field defaults to **`null`** (single) or **`[]`** (multiple).

```php
SelectField::make('category_id')
    ->default(1);
```

#### Validation rules

| Rule | When |
|------|------|
| `nullable` | Always (unless `required()`) |
| Selection shape | A single selection must be a scalar option identity. A multiselect must be a list of unique string/integer identities; duplicate identities, named indexes, nested values, booleans and floats in a list are rejected on the server. Backed enum identities remain supported. |
| Allowed values | Static options reject unknown and disabled keys. Relationships and custom label resolvers use Filament's selected-option validation. |
| Inline creation | `allowCreateOption()` accepts new identities but keeps the selection-shape check. Add rules for the allowed text, length and item count. |
| `required` | When `->required()` |
| `array` + `min:N` | When `->minItems(N)` on a multi-select |
| `array` + `max:N` | When `->maxItems(N)` on a multi-select |

---

### Configuration API

The signatures below show which settings accept closures. Closures use Filament's utility injection where supported.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `variant(string\|Closure $variant)` | Setup | `'bordered'` | Visual style: `bordered`, `secondary`, `flat`, `faded`, `soft`, `underlined`, `item-card` |
| `color(string\|Closure\|null $color)` | Setup | `null` | Accent color for the field |
| `chipColor(string\|Closure $chipColor)` | Setup | `'neutral'` | Color for multi-select chips |
| `keepSelectedOptionsInDropdown(bool\|Closure $condition = true)` | Setup | `false` | Multi-select: keep selected options in the dropdown with checkmarks (checklist) instead of removing them |
| `richOptions(bool\|Closure $condition = true)` | Setup | auto | Force rich option rendering |
| `optionDescriptionPosition(string\|Closure $position)` | Presentation | `below` | Put secondary text below the label or in a trailing column with `trailing` |
| `optionView(string\|Closure $view)` | Setup | — | Custom Blade (or Closure) per option row and trigger |
| `optionTriggerView(string\|Closure\|null $view)` | Setup | — | Optional separate view for closed trigger / chips |
| `optionViewData(array\|Closure $data)` | Setup | `[]` | Extra data passed into every option view |
| `richListTriggerDisplay(bool\|Closure $condition = true)` | Setup | `false` | Keep full list HTML in the closed trigger |
| `optionLayout(string\|Closure $layout)` | Setup | `'list'` | Dropdown layout: `list`, `grid` |
| `inlineFieldLabel(bool\|Closure $condition = true)` | Setup | `false` | Render label inside the field track |
| `inlineSearch(bool\|Closure $condition = true)` | Setup | `false` | Render search input inside the trigger (dropdown header search hidden) |
| `recentOptions(array\|Closure $values)` | Setup | `[]` | Pin recent option keys at the top of the dropdown (smart suggest) |
| `suggestedOptions(array\|Closure $values)` | Setup | `[]` | Pin suggested option keys below recent (smart suggest) |
| `allowCreateOption(bool\|Closure $condition = true)` | Setup | `false` | Inline “Create …” row from search (sets state to the string; **no** PHP create callback — see [Smart suggest](#smart-suggest-recentoptions-suggestedoptions-allowcreateoption) / [Create option: inline vs modal](#create-option-inline-vs-modal-which-api)) |
| `entityMentions(bool\|Closure $condition = true, string\|Closure $trigger = '@')` | Setup | `false` | Async people/entity picker; type the trigger in search or on the closed trigger |
| `clearable(bool\|Closure $condition = true)` | Setup | auto | Show clear button (×); also updates `selectablePlaceholder()` |
| `dropdownAlign(string\|Closure $align)` | Setup | `start` (`end` for `item-card`) | Align dropdown to the logical start or end of the field |
| `size(string\|ControlSize\|Closure $size)` | Setup | `'md'` | Control size: `sm`, `md`, `lg` |
| `rounding(string\|Closure\|null $rounding)` | Setup | config | Border radius token |
| `focusOutline(bool\|Closure $condition = true)` | Setup | `true` | Shows the field focus outline; pass `false` to disable it |
| `optionGroupSeparators(bool\|Closure $condition = true)` | Presentation | `true` | Dividers between option groups |
| `disabledOptions(array\|Closure $keys)` | Selection | `[]` | Disable particular keys; combines with `disableOptionWhen()` |
| `selectionIndicator(bool\|Closure $condition = true)` | Presentation | `true` | Show the selected-row indicator |
| `plainTrigger(bool\|Closure $condition = true)` | Presentation | `false` | Plain trigger presentation for compact composed controls |
| `chevronIcon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | Icons | Configured Hugeicon | Dropdown icon; `null` restores the default |
| `clearIcon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | Icons | Configured Hugeicon | Clear-selection icon |
| `selectedOptionCheckIcon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | Icons | Configured Hugeicon | Selected/checklist icon |
| `menuMaxHeight(int\|Closure\|null $pixels)` | Geometry | Automatic | Limit the menu height: 80–1200 px; `null` restores automatic sizing |
| `dropdownMinWidth(int\|Closure $pixels)` | Geometry | `288` | Minimum desktop panel width: 120–600 px, constrained by the viewport |
| `virtualizeThreshold(int\|Closure $items)` | Performance | `100` | Start virtualizing at 1–100,000 projected rows |
| `virtualRowHeight(int\|Closure $pixels)` | Performance | `36` | Initial list-row height estimate: 1–512 px; actual rows are measured |
| `virtualWindowSize(int\|Closure $items)` | Performance | `50` | Initial window estimate: 1–500; the measured viewport and overscan determine the mounted rows |
| `paginatedSearchResults(bool\|Closure $condition = true)` | Search | `false` | Enable paginated dynamic results |
| `searchResultsPageSize(int\|Closure $size = 50)` | Search | `50` | Requested rows per page; maximum comes from `select.max_page_size` (1000 by default) |
| `getSearchResultsPageUsing(?Closure $callback)` | Search | `null` | Return `items`, next `cursor` and `hasMore` |
| `searchCacheContextUsing(?Closure $callback)` | Search | `null` | Include tenant/policy/provider revision in this field's request-cache identity |
| `dependsOn(string\|array $paths, Closure $resolveOptions)` | Dependencies | None | Resolve options from one or several other field values |
| `resetOnDependencyChange(bool\|Closure $condition = true)` | Dependencies | `false` | Clear the dependent selection immediately when a parent changes, without waiting for the server |


#### `variant()`

```php
SelectField::make('category_id')->variant('soft');
SelectField::make('category_id')->variant('underlined');
```

#### `optionLayout()`

Use `grid` for a multi-column visual picker:

```php
SelectField::make('theme')
    ->options([
        'light' => ['label' => 'Light', 'icon' => 'hugeicons-sun-01'],
        'dark' => ['label' => 'Dark', 'icon' => 'hugeicons-moon-02'],
    ])
    ->optionLayout('grid');
```

#### `inlineSearch()`

Recommended for **single-select** searchable fields to keep the UI compact — the search field is the trigger, not a separate label swap:

```php
SelectField::make('user_id')
    ->searchable()
    ->inlineSearch();
```

When closed, the trigger input shows the selected label. When focused or open on **desktop**, the same input stays editable and keeps that label until you type or clear it; clearing the input clears the selection. On **mobile** (bottom sheet / drawer), search moves into the sheet header — the trigger cannot accept keyboard input while the drawer covers it. Use the default field clear (×) next to the chevron to reset the value — inline mode does not render a separate search-query × on desktop (that control exists in the dropdown/sheet search header).

**Ignored with `multiple()`:** `hasInlineSearch()` is `false` when the field is multi-select, even if you chain `inlineSearch()`. Multi-select fields should use dropdown / sheet header search (`searchable()` without relying on `inlineSearch()`).

For RTL layouts, set `extraAttributes(['dir' => 'rtl'])` on the field — the teleported **panel** and mobile **sheet** (bottom drawer) both copy the trigger writing direction onto the menu (`dir="rtl"`), so search icons, checkmarks (`inset-inline-end`), and option text mirror correctly. Search inputs use `dir="auto"` so Hebrew/Arabic queries get an RTL caret while Latin queries keep a normal LTR caret.

Works with `entityMentions()` — type `@` in the inline search input or press `@` on the closed trigger to start a mention query.

#### Field label, accent and rounding

Keep the label inside the field and choose its surface, accent and corner shape independently:

```php
SelectField::make('status')
    ->label('Status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->inlineFieldLabel()
    ->variant('secondary')
    ->color('primary')
    ->size('lg')
    ->rounding('full')
    ->focusOutline();
```

`inlineFieldLabel(false)` restores the outer label. `color(null)` restores the default accent. `rounding(null)` uses the global `ui.field_rounding` setting; `rounding('full')` creates a pill. The outer focus outline is enabled by default; `focusOutline(false)` disables it.

#### Secondary text beside the option label

```php
SelectField::make('plan')
    ->options([
        'starter' => ['label' => 'Starter', 'description' => '$19 / month'],
        'team' => ['label' => 'Team', 'description' => '$49 / month'],
    ])
    ->richOptions()
    ->optionDescriptionPosition('trailing');
```

The default `below` places the description beneath the label. `trailing` puts it at the logical end of the row, including RTL layouts. This configures the shared rich-option renderer; custom `optionView()` markup controls its own layout.

#### Smart suggest (`recentOptions`, `suggestedOptions`, `allowCreateOption`)

Pin curated rows at the top of the dropdown and optionally allow creating a new value from the current search:

```php
SelectField::make('project_id')
    ->searchable()
    ->recentOptions(['inbox', 'drafts'])
    ->suggestedOptions(['archived'])
    ->allowCreateOption();
```

Requires `searchable()`. When the query has **no exact label match**, a **Create “…”** row appears at the top of the list (label from `filament-flex-fields::default.select_field.smart_suggest.create`). Choosing that row commits the trimmed search string as the field value — **same string for both value and label**. No modal opens and **no PHP callback runs at click time**.

With `allowCreateOption()` enabled, a new text value does not have to match an existing option. The server still validates the selection structure. Add application rules for the text and its length:

```php
SelectField::make('slug')
    ->searchable()
    ->options([
        'inbox' => 'Inbox',
        'drafts' => 'Drafts',
    ])
    ->allowCreateOption()
    ->rule('string')
    ->rule('max:64')
    ->rule('regex:/^[a-z0-9][a-z0-9\-]*$/i');

// Multiple — each chip is a string; nest rules if you store an array:
SelectField::make('labels')
    ->multiple()
    ->searchable()
    ->options(['bug' => 'Bug', 'docs' => 'Docs'])
    ->allowCreateOption()
    ->nestedRecursiveRules(['string', 'max:32', 'regex:/^[a-z0-9\-]+$/i']);
```

| | Single (`allowCreateOption()`) | Multiple (`->multiple()->allowCreateOption()`) |
|--|--|--|
| State after create | `string` (the typed text) | `array` of strings (each create adds a chip) |
| Dropdown | Create row when search ≠ any option label | Same; created values become chips |
| Server / DB | Not created automatically; package does not block the new string key | Same |

Persist created values after your form has been validated. Inline creation changes the field value; it does not insert a database record. This example limits each label and the number of selections:

```php
SelectField::make('labels')
    ->multiple()
    ->searchable()
    ->options(['Laravel' => 'Laravel', 'PHP' => 'PHP'])
    ->allowCreateOption()
    ->maxItems(20)
    ->nestedRecursiveRules(['string', 'max:80']);
```

In your application's save action, authorize the operation and persist the validated labels within the transaction used for the form. If you need a numeric model key, prefer the modal flow below.

#### Create option: inline vs modal (which API?)

Choose inline creation for a text value, or a modal for a record with its own fields and validation:

| | Inline smart suggest | Filament modal create |
|--|--|--|
| API | `allowCreateOption()` | `createOptionForm()` + `createOptionUsing()` (+ optional `createOptionAction()`) |
| UI | “Create …” row inside the dropdown | Suffix / manage action opens a form modal |
| Input | Current search string only | Full form schema (name, email, …) |
| On confirm | Sets state to that string (client) | Runs `createOptionUsing` on the server; return value becomes the selected option key |
| Best for | Free-text tags, one-field labels, quick add | Eloquent / relationship records with validation and multiple attributes |

<Tabs>
  <Tab title="Name only">
    A create modal does not require extra fields. This example asks only for a name, validates it and returns the new author's key. `Author` is your application's model.

```php
use App\Models\Author;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('author_id')
    ->relationship(name: 'author', titleAttribute: 'name')
    ->searchable()
    ->createOptionForm([
        FlexTextInput::make('name')
            ->required()
            ->maxLength(120),
    ])
    ->createOptionUsing(fn (array $data): int => Author::create([
        'name' => $data['name'],
    ])->getKey());
```

    The model must allow creation with only `name` (including any required database columns). The newly created record becomes the selected option.
  </Tab>
  <Tab title="Additional fields">
    Add any FLEX fields and schema layouts your record needs. Each field keeps its own validation. This example adds an email address and widens the modal.

```php
use App\Models\Author;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Filament\Actions\Action;

SelectField::make('author_id')
    ->relationship(name: 'author', titleAttribute: 'name')
    ->searchable()
    ->preload()
    ->createOptionForm([
        FlexTextInput::make('name')
            ->required()
            ->maxLength(120),
        FlexTextInput::make('email')
            ->email()
            ->required()
            ->maxLength(254),
    ])
    ->createOptionUsing(fn (array $data): int => Author::create($data)->getKey())
    ->createOptionAction(fn (Action $action) => $action->modalWidth('3xl'));
```

    `createOptionForm()` also accepts a closure for a dynamic schema. Without `relationship()`, maintain `options()` and resolve newly created labels with `getOptionLabelUsing()`. Authorize record creation in your application's create callback or action before persisting data.
  </Tab>
</Tabs>

Use the inline flow for free-text values. Use a modal when a new record needs validated fields, permissions or a database-generated key.

#### Entity mentions (`entityMentions()`)

Async people/entity picker triggered with `@` in the closed trigger, inline search input, or dropdown search:

```php
SelectField::make('assignees')
    ->multiple()
    ->searchable()
    ->entityMentions(trigger: '@')
    ->getSearchResultsUsing(fn (string $search): array => User::query()
        ->where('name', 'like', "%{$search}%")
        ->limit(20)
        ->pluck('name', 'id')
        ->all());
```

Selected mention chips show the mention prefix. Use `getOptionLabelsUsing()` to resolve saved selections in a multiselect, or `getOptionLabelUsing()` for a single selection.

---

### Real-world examples

#### User select with avatars

```php
SelectField::make('user_id')
    ->label('Assignee')
    ->options(fn (): array => User::query()->orderBy('name')->limit(50)->get()->mapWithKeys(fn ($user) => [
        $user->id => [
            'label' => $user->name,
            'description' => $user->email,
            'image' => $user->avatar_url,
        ],
    ])->all())
    ->searchable()
    ->inlineSearch()
    ->variant('soft');
```

#### Multi-select tags in a Section

```php
Section::make('Metadata')
    ->schema([
        SelectField::make('tags')
            ->multiple()
            ->relationship('tags', 'name')
            ->chipColor('success')
            ->preload(),
    ]);
```

---

### Grouped sections

Nest option groups in `options()` to render section headers in the dropdown. Enable explicit dividers between groups (default on):

```php
SelectField::make('country')
    ->searchable()
    ->optionGroupSeparators()
    ->options([
        'North America' => [
            'usa' => 'United States',
            'canada' => 'Canada',
        ],
        'Europe' => [
            'france' => 'France',
            'germany' => 'Germany',
        ],
    ]);
```

Use `optionGroupSeparators(false)` to fall back to header-only dividers.

---

### Disabled options

Disable specific keys while keeping them visible in the list:

```php
SelectField::make('animal')
    ->searchable()
    ->disabledOptions(['cat', 'kangaroo'])
    ->options([
        'dog' => 'Dog',
        'cat' => 'Cat',
        'bird' => 'Bird',
    ]);
```

`disableOptionWhen()` remains available for dynamic rules.

---

### Async search with load more

For large remote datasets, paginate search results and append rows as the user scrolls:

```php
SelectField::make('character')
    ->searchable()
    ->paginatedSearchResults()
    ->searchResultsPageSize(20)
    ->getOptionLabelUsing(fn (string $value): ?string => Character::find($value)?->name)
    ->getSearchResultsPageUsing(function (string $search, ?string $cursor, int $pageSize): array {
        [$items, $nextCursor, $hasMore] = CharacterRepository::search($search, $cursor, $pageSize);

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'hasMore' => $hasMore,
        ];
    });
```

Define `CharacterRepository::search()` in your application. Each item contains a stable `value` and its display `label`. Rich rows may also contain `description`, `image`, `icon` and `disabled`. For example, the final page can return:

```php
return [
    'items' => [
        ['value' => 'luke', 'label' => 'Luke Skywalker'],
        ['value' => 'leia', 'label' => 'Leia Organa'],
    ],
    'cursor' => null,
    'hasMore' => false,
];
```

When more pages exist, return `hasMore => true` and a nonempty next cursor. Keep that cursor opaque, stable and advancing; never return the previous cursor. Return at most `$pageSize` option records. Group records may use `['label' => 'Group name', 'options' => [...]]`; their option leaves share the same page-size limit. The selected-label resolver must authorize and resolve the same catalog. The trigger shows a loading indicator while the first page loads or while a new query is debounced. A footer spinner appears while additional pages load.

Closure-based `options()` are resolved when the menu opens. A saved selection may need a label before opening: provide `getOptionLabelUsing()` or `getOptionLabelsUsing()` rather than fetching an entire catalog just to display its selected value.

> **Pagination note:** true “load more” paging requires `getSearchResultsPageUsing()`. Without it, the field may still call a non-paginated search callback and will set `hasMore=false` after the first page so the client does not spin forever.

> **Search rate limit:** Livewire search endpoints for Select / Tags / IconPicker are capped by `filament-flex-fields.select.search_rate_limit_per_minute` (default 60/min per actor+field). The limiter keys on the authenticated user id when present, otherwise `Request::ip()` — configure Laravel **TrustedProxies** correctly behind a load balancer so spoofed `X-Forwarded-For` headers are not trusted.

---

### FlexFieldFormBuilder / Studio config

`SelectFieldConfigurator` (Studio / `FlexFieldFormBuilder`) maps these config keys onto the fluent API:

| Config key | Maps to |
|------------|---------|
| `options` | `options()` — Studio list rows `{ value, label }` are normalized to `value => label` |
| `searchable` | `searchable()` |
| `multiple` | `multiple()` |
| `native` | `native()` |
| `clearable` | `clearable()` |
| `variant` | `variant()` |
| `size` | `size()` |
| `inline_search` | `inlineSearch()` (ignored when `multiple` is true) |
| `keep_selected_options_in_dropdown` | `keepSelectedOptionsInDropdown()` (no-op unless multiple) |
| `rich_options`, `rich_list_trigger_display` | `richOptions()`, `richListTriggerDisplay()` |
| `option_view`, `option_trigger_view`, `option_layout` | Custom views and list/grid presentation |
| `chip_color`, `color` | Chip and control accent colors |
| `dropdown_align` | `dropdownAlign()` |
| `chevron_icon`, `clear_icon` | Trigger icons |
| `disabled` | `disabled()` |

**PHP-only (not configured from Studio):** async search (`getSearchResultsUsing()`, `getSearchResultsPageUsing()`, `paginatedSearchResults()`), `relationship()`, `dependsOn()`, create/edit option modals, and other Livewire search contracts. Wire those on the field in PHP.

`variant('item-card')` defaults clearable off unless `clearable` is set explicitly in config or via `clearable()`.

---

### Playground

`/admin/flex-fields/select-field`

Open the [live Select demo](https://demo.bjanczak.com/admin/flex-fields-playground/select-field), or follow [Installation](/docs/installation) to enable the local playground.

---

### Filament Select parity

`SelectField` **extends** `Filament\Forms\Components\Select`. Every public Select API from the Filament docs is available with the same method names. The headless Alpine/Livewire UI wires behaviour that Filament’s JS select would otherwise handle.

**Out of scope for this field (separate Filament components):** `MorphToSelect`, `ModalTableSelect`. Use those classes when you need morph type pickers or table-in-modal selection.

#### Coverage matrix (Filament Select docs → SelectField)

| Filament API | Support | Notes |
|--------------|---------|-------|
| `options([...])` / `options(fn)` | Yes | Static payload or `getOptionsForJs` on open (closures deferred until open) |
| `native(false)` / `native(true)` | Yes | FLEX dropdown by default. `native(true)` uses an HTML select for a plain single selection; search, multiple, rich/HTML options and other enhanced features use the FLEX renderer. Fluent call order does not change the decision |
| `searchable()` / `searchable(bool\|Closure)` / `searchable(['col', …])` | Yes | Client filter or relationship SQL columns |
| `getSearchResultsUsing()` + `getOptionLabelUsing()` | Yes | Async search + label hydrate; required for valid-option validation |
| `getOptionLabelsUsing()` (multiple) | Yes | Same as Filament multi-select |
| `loadingMessage()` | Yes | Skeleton / loading empty state |
| `searchingMessage()` | Yes | While debounced search is in flight |
| `searchPrompt()` | Yes | Search-input placeholder for local and server lists, inline search and the mobile sheet. Defaults to the active Filament translation |
| `searchLabels()` / `searchValues()` | Yes | Choose whether local search matches labels, option keys or both |
| `noSearchResultsMessage()` | Yes | Including static searchable lists |
| `noOptionsMessage()` | Yes | Empty list / preload with no rows |
| `searchDebounce()` | Yes | Default 1000 ms (same as Filament) |
| `optionsLimit()` | Yes | Caps rendered options when the list is below the virtualize threshold (default 50, Filament parity). At ≥ virtualize threshold, virtualization owns the DOM budget and the limit is not applied to the in-memory list |
| `multiple()` | Yes | Array state; cast on the model |
| `reorderable()` | Yes | Drag chips when multiple |
| `minItems()` | Yes | PHP `array` + `min:N` validation (same as Filament — no client gate) |
| `maxItems()` / `maxItemsMessage()` | Yes | PHP `max:N` **and** client block + in-menu banner |
| Grouped `options(['Group' => [...]])` | Yes | Flat dropdown rows + group headers |
| `relationship()` | Yes | BelongsTo / BelongsToMany; query restrict wrapper |
| `preload()` | Yes | |
| `ignoreRecord` (relationship arg) | Yes | Inherited |
| `modifyQueryUsing` (relationship arg) | Yes | Inherited |
| `getOptionLabelFromRecordUsing()` | Yes | Inherited |
| `pivotData()` | Yes | Inherited |
| `createOptionForm()` / `createOptionUsing()` | Yes | Filament **modal** create (server callback returns option key). Not the same as `allowCreateOption()` — see [Create option: inline vs modal](#create-option-inline-vs-modal-which-api) |
| `editOptionForm()` / `updateOptionUsing()` | Yes | Same action path |
| `createOptionAction()` / `editOptionAction()` / `manageOptionActions()` | Yes | Inherited action customizers |
| `allowHtml()` | Yes | Sanitized when using package option views |
| `wrapOptionLabels(false)` | Yes | Truncate overflowing labels |
| `selectablePlaceholder(false)` | Yes | Disables clear / null re-select (`isClearableInUi()`) |
| `clearable()` | Yes | Flex Fields alias that also sets `selectablePlaceholder()` |
| `disableOptionWhen()` / `disabledOptions()` | Yes | Filament callback + Flex Fields key list |
| `prefix()` / `suffix()` / `prefixIcon()` / `suffixIcon()` / icon colors | Yes | Filament input wrapper / inline affixes |
| `boolean()` / true/false/placeholder labels | Yes | Inherited |
| `position('top'\|'bottom')` | Yes | Forces menu above/below (auto-flip when unset) |
| `forceSearchCaseInsensitive()` | Yes | Inherited search behaviour |
| `dependsOn()` | Yes | Flex Fields helper; parent must be `->live()`; reopen dropdown after parent change |

#### Basic options & JS select

```php
SelectField::make('status')
    ->options([
        'draft' => 'Draft',
        'reviewing' => 'Reviewing',
        'published' => 'Published',
    ]);
// The FLEX dropdown is the default; use native(true) for a plain HTML select.
```

#### Searching & custom messages

Every searchable dropdown receives a translated placeholder by default (`Start typing to search...` in English). Use `searchPrompt()` to replace the search text. `placeholder()` separately controls the closed field when no option is selected; it does not replace the dropdown's search placeholder.

```php
SelectField::make('country')
    ->options(['pl' => 'Poland', 'us' => 'United States'])
    ->searchable()
    ->placeholder('Choose a country')
    ->searchPrompt('Find a country…');
```

The same setting works with remote results and `inlineSearch()`, including the mobile search header. A closure may supply a context-dependent prompt. An explicitly empty `searchPrompt('')` hides the text intentionally.

To search by keys as well as labels:

```php
SelectField::make('country_code')
    ->options(['PL' => 'Poland', 'US' => 'United States'])
    ->searchable()
    ->searchLabels()
    ->searchValues();
```

```php
SelectField::make('author_id')
    ->searchable()
    ->getSearchResultsUsing(fn (string $search): array => User::query()
        ->where('name', 'like', "%{$search}%")
        ->limit(50)
        ->pluck('name', 'id')
        ->all())
    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
    ->loadingMessage('Loading authors...')
    ->searchingMessage('Searching authors...')
    ->noSearchResultsMessage('No authors found.')
    ->noOptionsMessage('No authors available.')
    ->searchPrompt('Search authors by name')
    ->searchDebounce(500)
    ->optionsLimit(20);
```

#### Multi-select, reorder, min/max items

```php
SelectField::make('technologies')
    ->multiple()
    ->reorderable()
    ->options([
        'tailwind' => 'Tailwind CSS',
        'alpine' => 'Alpine.js',
        'laravel' => 'Laravel',
        'livewire' => 'Laravel Livewire',
    ])
    ->minItems(1)   // form validation when fewer than 1 selected
    ->maxItems(3)   // validation + blocks further picks in the UI
    ->maxItemsMessage('Remove an item before adding another.');
```

For custom async multi-select labels use `getOptionLabelsUsing()` (plural), same as Filament.

#### Grouped options

```php
SelectField::make('status')
    ->searchable()
    ->options([
        'In Process' => [
            'draft' => 'Draft',
            'reviewing' => 'Reviewing',
        ],
        'Reviewed' => [
            'published' => 'Published',
            'rejected' => 'Rejected',
        ],
    ]);
```

#### Relationship (+ preload, create/edit modals, pivot)

```php
SelectField::make('author_id')
    ->relationship(name: 'author', titleAttribute: 'name')
    ->searchable(['name', 'email'])
    ->preload()
    ->createOptionForm([
        FlexTextInput::make('name')->required(),
        FlexTextInput::make('email')->email()->required(),
    ])
    ->createOptionUsing(fn (array $data): int => Author::create($data)->getKey())
    ->createOptionAction(fn (Action $action) => $action->modalWidth('3xl'))
    ->editOptionForm([
        FlexTextInput::make('name')->required(),
        FlexTextInput::make('email')->email()->required(),
    ])
    ->updateOptionUsing(function (array $data, Schema $schema): void {
        $schema->getRecord()?->update($data);
    });
```

Inline smart-suggest create (`allowCreateOption()`) is documented under [Create option: inline vs modal](#create-option-inline-vs-modal-which-api): one-field create from the search string, no modal, no automatic DB insert. Use `createOptionForm()` / `createOptionUsing()` when the new record needs multiple fields or a server-side primary key.

Call `disabled()` **before** `relationship()` on multi relationship selects (Filament requirement).

#### HTML labels, wrap, placeholder, disabled options, affixes, boolean, position

```php
SelectField::make('technology')
    ->options([
        'tailwind' => '<span class="text-blue-500">Tailwind</span>',
    ])
    ->searchable()
    ->allowHtml()
    ->wrapOptionLabels(false)
    ->selectablePlaceholder(false)
    ->disableOptionWhen(fn (string $value): bool => $value === 'published')
    ->prefix('https://')
    ->suffix('.com')
    ->suffixIcon('hugeicons-globe-02')
    ->suffixIconColor('success')
    ->position('bottom'); // or 'top'

SelectField::make('feedback')
    ->boolean(trueLabel: 'Absolutely!', falseLabel: 'Not at all!', placeholder: 'Make your mind up...');
```

#### Cascading options (`dependsOn`)

```php
use Filament\Schemas\Components\Utilities\Set;

SelectField::make('country')
    ->live()
    ->options(['pl' => 'Poland', 'us' => 'United States'])
    ->afterStateUpdated(fn (Set $set) => $set('region', null));

SelectField::make('region')
    ->resetOnDependencyChange()
    ->dependsOn('country', fn (?string $country): array => match ($country) {
        'pl' => ['mz' => 'Mazowieckie'],
        'us' => ['ca' => 'California', 'tx' => 'Texas'],
        default => [],
    })
    ->searchable()
    ->placeholder('Pick a country first');
```

Until the parent has a value, the region list is correctly empty. Choosing Poland → Mazowieckie and then changing the country to United States clears the region immediately through `resetOnDependencyChange()`. The next region list contains California and Texas.

`dependsOn()` refreshes dependent choices; it does not decide whether an existing selection should be preserved. Enable `resetOnDependencyChange()` on the child for an immediate browser reset, including a non-clearable field. It clears a single selection to `null` and a multiselect to `[]`. It is off by default so dependencies that only change labels or ordering do not unexpectedly clear data.

Keep the parent's explicit PHP reset callback for server-driven state updates and the server-side form lifecycle. For a dependent multiselect, use `$set('region', [])` instead of `null`. Server-side Select validation also rejects a submitted region that is not in the current country's allowed options. A client reset is not a replacement for validation or authorization.

Use `dependsOn(['country', 'service'], fn (?string $country, ?string $service): array => ...)` for several dependencies; callback arguments follow the path order. Paths are relative to the field's schema, including repeater items. Reset a child's previous selection when it no longer belongs to the parent's allowed choices.

If only particular fields need a refresh, use Filament's partial-render configuration for those fields. Skip rendering only when no other visible content depends on the changed value.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [FlexRadiolist](/docs/flexradiolist) | When all options should be visible at once |
| [ChoiceCards](/docs/choicecards) | Large card-style selection with more detail |
| [DualListboxField](/docs/duallistboxfield) | When managing large sets of selected items |
| `Filament\Forms\Components\MorphToSelect` | MorphTo type + record pickers |
| `Filament\Forms\Components\ModalTableSelect` | Select records from a Filament table modal |

---

### Large catalogs and virtualization

<Tabs>
  <Tab title="Local catalog">
    Use `options()` when the catalog is already available locally. Virtualization reduces mounted rows; it does not avoid sending the catalog to the browser.

```php
SelectField::make('product_code')
    ->options($productOptions)
    ->searchable()
    ->virtualizeThreshold(100)
    ->virtualRowHeight(40)
    ->virtualWindowSize(50)
    ->menuMaxHeight(360);
```

    `$productOptions` is an application-provided key/label map. The default threshold is 100 projected rows. Rows are measured as they appear, and the visible window follows the viewport. A grid uses measured grid-row geometry as well.

    `optionsLimit(50)` caps the display of a small nonvirtualized result set. Once virtualization is active, the full matching collection stays navigable; the mounted window controls the DOM budget.
  </Tab>
  <Tab title="Remote catalog">
    Use `getSearchResultsUsing()` with a bounded database query for server search, or `getSearchResultsPageUsing()` for cursor pagination. Pagination limits transferred results; virtualization limits mounted rows. They can work together.

```php
use App\Models\Product;

SelectField::make('product_id')
    ->searchable()
    ->searchDebounce(250)
    ->getSearchResultsUsing(fn (string $search): array => Product::query()
        ->where('name', 'like', "%{$search}%")
        ->limit(50)
        ->pluck('name', 'id')
        ->all())
    ->getOptionLabelUsing(
        fn (string $value): ?string => Product::find($value)?->name,
    );
```

    Apply the same authorization and tenant scope to search and selected-label queries. The search debounce applies to fetching results, not to opening the menu.
  </Tab>
</Tabs>

#### Menu dimensions

```php
SelectField::make('category')
    ->options(['news' => 'News', 'events' => 'Events'])
    ->dropdownMinWidth(320)
    ->menuMaxHeight(400)
    ->dropdownAlign('start');
```

Menus fit within the viewport. Automatic placement chooses above or below the trigger according to available space. `position('top')` and `position('bottom')` request a particular side. Width and height settings are bounded; unsupported values produce a configuration error rather than an oversized panel. On mobile, the sheet owns its own responsive layout.

#### Search limits and cache

Publish/configure `config/filament-flex-fields.php` when you need different limits:

```php
'select' => [
    'max_page_size' => 1000,
    'max_page_bytes' => 1048576,
    'max_search_bytes' => 4096,
    'max_search_characters' => 512,
    'search_cache_entries' => 64,
    'search_cache_bytes' => 4194304,
    'search_rate_limit_per_minute' => 60,
],
```

The server cache belongs to one field and one request. It avoids repeated provider work within that request; it is not a cross-request cache. Cache identities include the field, record, configured dependencies and provider. For context that can change within a request, supply its revision:

```php
SelectField::make('category_id')
    ->relationship('category', 'name')
    ->searchable()
    ->searchCacheContextUsing(fn (): array => [
        'tenant' => auth()->user()?->tenant_id,
        'locale' => app()->getLocale(),
    ]);
```

This adds cache context; it does not apply tenant authorization to the query. Changing `options()` invalidates the field's request cache. `invalidateSelectSearchCache()` is available when your application changes a provider's result within the same request.

Page payloads must be JSON-compatible and remain within the configured size. Cursors must progress: repeated/cyclic cursors and malformed responses are rejected. On a failed load, already displayed options remain available and the menu provides retry feedback.

### Icons and compact controls

```php
SelectField::make('status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->chevronIcon('hugeicons-unfold-more')
    ->clearIcon('hugeicons-cancel-01')
    ->selectedOptionCheckIcon('hugeicons-tick-02');
```

Icons use the package's shared icon rendering. Passing `null` to an icon setter restores its configured default.

```php
SelectField::make('density')
    ->options(['comfortable' => 'Comfortable', 'compact' => 'Compact'])
    ->plainTrigger()
    ->selectionIndicator(false)
    ->clearable(false);
```

Use `plainTrigger()` when embedding a small select inside another control. `selectionIndicator(false)` removes the selected-row marker without changing the selected value.

### Keyboard, mobile and RTL

| Interaction | Result |
|---|---|
| Enter or Space on the trigger | Open the menu |
| Arrow Down / Arrow Up | Open or navigate selectable rows |
| Home / End on the noneditable trigger | Move to the first / last selectable row |
| Enter while navigating | Select the active row |
| Escape | Close and return to the control |
| Typing in search | Filter local options or request server results |
| Clear selection | Reset the value when clearing is allowed |

Home and End inside a text input retain their normal caret behavior. Disabled rows cannot be selected. Multi-select checklists remain open while items are toggled; chips can be removed individually.

On narrow viewports, the menu becomes a sheet with its own search area. Open lists respond to window resizing and use touch-sized rows. The menu carries the field's writing direction:

```php
SelectField::make('city')
    ->options(['riyadh' => 'الرياض', 'jeddah' => 'جدة'])
    ->searchable()
    ->extraAttributes(['dir' => 'rtl'])
    ->dropdownAlign('end');
```

### Saving selections to your database

Save a single selection to a suitable scalar column. The option key is saved, not its display label. A multiselect without a relationship can be saved in a JSON column with an array cast:

```php
protected function casts(): array
{
    return [
        'labels' => 'array',
    ];
}
```

For `relationship('tags', 'name')->multiple()`, Filament saves the relationship; a JSON column is unnecessary. `pivotData()` can supply additional pivot attributes. Always authorize related records in your application.

Use the form's validated state when saving. For a constrained multiselect:

```php
SelectField::make('permissions')
    ->options(['read' => 'Read', 'write' => 'Write', 'publish' => 'Publish'])
    ->multiple()
    ->required()
    ->minItems(1)
    ->maxItems(2)
    ->maxItemsMessage('Choose up to two permissions.');
```

Minimum counts are checked on submit; maximum counts are checked on the server and block additional picks in the menu. A disabled control does not replace server authorization.

### Troubleshooting

<AccordionGroup>
  <Accordion title="The menu opens, but search results take time">
    Server search uses `searchDebounce()` (1000 ms by default) before sending a request. Lower it deliberately, for example to 250 ms. Bound database queries and check their indexes. Remote results also depend on network and server response time.
  </Accordion>
  <Accordion title="An existing selection shows its key instead of its label">
    For remote options, provide `getOptionLabelUsing()` for a single value or `getOptionLabelsUsing()` for a multiselect. These resolvers must also be able to resolve saved records outside the current result page.
  </Accordion>
  <Accordion title="A previously selected value fails validation">
    Check whether the option is now disabled, no longer belongs to the dependent catalog, or is excluded by your relationship scope. Reset invalid child selections when their parent changes.
  </Accordion>
  <Accordion title="A new label is rejected">
    `allowCreateOption()` accepts a new text identity; `createOptionForm()` creates a record through a validated modal. Use the flow that matches your database column. Additional application rules still apply.
  </Accordion>
  <Accordion title="Only some JSON builder settings are available">
    The builder configuration table lists the supported declarative keys. Callbacks, relationships, pagination and dependencies belong in PHP. Do not assume every fluent method has a JSON key.
  </Accordion>
</AccordionGroup>

For shared loading behavior, see [Asset loading](/docs/asset-loading).
