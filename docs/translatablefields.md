---
title: "TranslatableFields"
description: "Group translated Filament fields into locale tabs with shared validation, configurable languages and optional Spatie Translatable support."
icon: "languages"
"canonical": "https://flex-fields.bjanczak.com/docs/translatablefields"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/translatable-fields.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "TranslatableFields in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/translatable-fields-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/translatable-fields-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/translatable-fields.webp"
"twitter:image:alt": "TranslatableFields in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/translatablefields#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/translatablefields",
      "name": "TranslatableFields",
      "description": "Group translated Filament fields into locale tabs with shared validation, configurable languages and optional Spatie Translatable support.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/translatablefields#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/translatablefields#article",
      "url": "https://flex-fields.bjanczak.com/docs/translatablefields",
      "headline": "TranslatableFields",
      "description": "Group translated Filament fields into locale tabs with shared validation, configurable languages and optional Spatie Translatable support.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/translatablefields#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/translatable-fields-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/translatable-fields-motion.mp4#video",
      "name": "TranslatableFields — FLEX Fields demonstration",
      "description": "A recorded demonstration of TranslatableFields in the FLEX Fields playground for Filament. Group translated Filament fields into locale tabs with shared validation, configurable languages and optional Spatie Translatable support.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/translatable-fields.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.25S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/translatable-fields-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/translatablefields#article"
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
  src="/art/previews/translatable-fields-motion.mp4"
  poster="/art/previews/translatable-fields.webp"
  width="1920"
  height="1080"
  aria-label="TranslatableFields — FLEX Fields demonstration"
>
  <a href="/art/previews/translatable-fields-motion.mp4">Watch the TranslatableFields demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)


### Summary

Locale-aware **schema layout** built on **SegmentTabs** (segmented tabs, same visual language as [SegmentControl](/docs/segmentcontrol)). Clones one or more field templates into per-locale tabs with automatic state paths, JSON hydration, and optional Spatie `laravel-translatable` support.

Designed as a first-party, extensible alternative to third-party translatable tab packages — with explicit extension points (`localeFieldUsing`, `storageAttributeUsing`, tab/field modifiers) and no external plugin dependency.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields` |
| **Extends** | `SegmentTabs` |
| **Legacy alias** | `TranslatableTabs` (drop-in namespace swap from `abdulmajeed-jamaan/filament-translatable-tabs`) |
| **Field macro** | `Field::translatableFields()` / `Field::translatableTabs()` |
| **Component type** | Schema / layout (not a form field) |
| **Form state** | Dot paths per locale: `title.ar`, `title.en`, … |
| **DB storage** | JSON / array column: `{"ar":"…","en":"…"}` or Spatie translatable attribute |

> **Not the same as TitleSlugField translations.** [TitleSlugField](/docs/slugfield-and-titleslugfield#translatable-titles-single-slug) provides a narrower use case: translatable **titles** with a **single shared slug** and slug-source-locale sync. Use `TranslatableFields` for any generic translatable attribute (body, excerpt, metadata, …).

### Basic usage — standalone component

Register field templates with `-&gt;schema()`. Each template is cloned once per locale tab.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;

TranslatableFields::make('Content')
    ->locales(['ar' => 'Arabic', 'en' => 'English'])
    ->withRecommendedDefaults()
    ->schema([
        FlexTextInput::make('title')->hiddenLabel(),
        FlexTextareaField::make('body')->hiddenLabel(),
    ]);
```

On save, Filament merges `title.ar` / `title.en` (and `body.ar` / `body.en`) into nested arrays on the model. On edit, values are hydrated from JSON columns automatically.

### Basic usage — field macro

Wrap a single field in locale tabs without declaring `TranslatableFields` explicitly. The macro returns the `TranslatableFields` component (replace the field in your schema with the return value).

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('title')
    ->label('Title')
    ->translatableFields(['ar', 'en']);
```

The macro uses the field's label as the segment-tabs heading and wraps the field as the sole template.

#### Macro with inline modifiers

```php
use Filament\Forms\Components\Field;

FlexTextInput::make('title')
    ->label('Title')
    ->translatableFields(
        locales: ['pl', 'en'],
        modifyTabsUsing: fn ($tab, string $locale) => null,
        modifyFieldsUsing: fn (Field $field, string $locale) => $field
            ->placeholder("Title ({$locale})"),
    );
```

| Macro parameter | Type | Description |
|-----------------|------|-------------|
| `$locales` | `array\|Closure\|null` | Locale codes or `locale =&gt; label` map. `null` = read from config. |
| `$modifyTabsUsing` | `Closure(TranslatableTab, string $locale): void\|null` | Applied to each locale tab after build. |
| `$modifyFieldsUsing` | `Closure(Field, string $locale): void\|null` | Applied to each cloned field after build. |

### Legacy aliases

For projects migrating from `abdulmajeed-jamaan/filament-translatable-tabs`:

```php
// Class alias — swap import only
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableTabs;

TranslatableTabs::make('Content')
    ->locales(['de' => 'DE', 'en' => 'EN'])
    ->schema([FlexTextInput::make('title')]);

// Field macro alias
FlexTextInput::make('title')->translatableTabs(['de', 'en']);
```

Third-party preset method names are also aliased on the component:

| Preferred method | Migration alias |
|------------------|-----------------|
| `directionByLocale(TranslatableDirectionScope $scope = Auto)` | `addDirectionByLocale()` |
| `emptyBadgeWhenAllFieldsAreEmpty()` | `addEmptyBadgeWhenAllFieldsAreEmpty()` |
| `activeTabWithValue()` | `addSetActiveTabThatHasValue()` |

### Single field vs multiple fields

#### Single field

One template field → one input per locale tab. Ideal for titles, names, short strings.

```php
TranslatableFields::make('Title')
    ->locales(['ar' => 'Arabic', 'en' => 'English'])
    ->directionByLocale()
    ->emptyBadgeWhenAllFieldsAreEmpty()
    ->activeTabWithValue()
    ->schema([
        FlexTextInput::make('title')->label('Title')->hiddenLabel(),
    ]);
```

Or via macro:

```php
FlexTextInput::make('title')->label('Title')->translatableFields(['ar', 'en']);
```

#### Multiple fields (group)

Several templates share the same locale tabs — all fields in a tab belong to that locale.

```php
TranslatableFields::make('Article')
    ->locales(['ar' => 'Arabic', 'en' => 'English'])
    ->withRecommendedDefaults()
    ->schema([
        FlexTextInput::make('title')->hiddenLabel(),
        FlexTextareaField::make('body')->hiddenLabel(),
        FlexTextareaField::make('excerpt')->hiddenLabel(),
    ]);
```

Empty-badge logic considers **all** fields in a tab: the badge appears only when every field in that locale tab is empty.

### Locales configuration

Locales can be supplied inline, split across codes and labels, or read from config.

#### Inline map (`locale =&gt; label`)

```php
TranslatableFields::make('Content')
    ->locales(['pl' => 'Polski', 'en' => 'English'])
    ->schema([FlexTextInput::make('title')]);
```

#### List of codes + separate labels

```php
TranslatableFields::make('Content')
    ->locales(['ar', 'en'])
    ->localesLabels([
        'ar' => __('locales.ar'),
        'en' => __('locales.en'),
    ])
    ->schema([FlexTextInput::make('title')]);
```

When only codes are given and no matching label exists, the tab label defaults to the uppercased locale code (`en` → `EN`).

#### From config (omit `-&gt;locales()`)

```php
// config/filament-flex-fields.php
'translatable' => [
    'locales' => ['ar', 'en'],
    'locale_labels' => ['ar' => 'Arabic', 'en' => 'English'],
],
```

```php
TranslatableFields::make('Content')
    ->schema([FlexTextInput::make('title')]);
```

If `translatable.locales` is unset, the resolver falls back to `slug.translatable_locales`.

Both `locales()` and `localesLabels()` accept `Closure` for dynamic resolution (e.g. tenant-specific languages).

### Production presets

Bundled helpers for common production UX. Combine individually or use the bundle.

#### `withRecommendedDefaults(?string $emptyBadgeLabel = null)`

Applies all three presets below. Pass a custom empty-badge label or rely on config (`translatable.empty_badge_label`, default `'empty'`).

```php
TranslatableFields::make('Article')
    ->locales(['ar' => 'Arabic', 'en' => 'English'])
    ->withRecommendedDefaults('missing')
    ->schema([FlexTextInput::make('title')]);
```

#### `borderedPanels(bool $condition = true)`

Adds class `fff-translatable-fields--bordered` so the active tab panel renders inside a bordered card (`rounded-xl`, padding). **Off by default** — fields sit flush under the locale tabs. Use when you want a contained content area (e.g. multi-field groups in a Section).

```php
TranslatableFields::make('Article')
    ->locales(['pl' => 'PL', 'en' => 'EN'])
    ->borderedPanels()
    ->schema([
        FlexTextInput::make('title'),
        FlexTextareaField::make('body'),
    ]);
```

#### `directionByLocale(TranslatableDirectionScope $scope = TranslatableDirectionScope::Auto)`

Sets `dir="rtl"` or `dir="ltr"` per locale tab. Default list from config:

```php
'translatable' => [
    'rtl_locales' => ['ar', 'he', 'fa', 'ur'],
],
```

Locales such as `ar-SA` or `he_IL` match by **primary language subtag** (BCP 47).

By default (`Auto`), direction is applied to the **editable control** (`extraInputAttributes()`) when the field supports Filament's `HasExtraInputAttributes` — labels and helper text keep the panel direction. Fields without a native input (custom layouts) still receive `dir` on the field wrapper so nothing breaks.

```php
use Bjanczak\FilamentFlexFields\Enums\TranslatableDirectionScope;

TranslatableFields::make('Content')
    ->directionByLocale(); // Auto — input when supported

TranslatableFields::make('Content')
    ->directionByLocale(TranslatableDirectionScope::Field); // legacy wrapper behavior
```

#### `emptyBadgeWhenAllFieldsAreEmpty(?string $emptyLabel = null)`

Shows a warning badge on locale tabs where **all** schema fields are empty. Useful on edit forms to spot untranslated locales.

```php
TranslatableFields::make('Content')
    ->locales(['ar', 'en'])
    ->emptyBadgeWhenAllFieldsAreEmpty(__('locales.empty'))
    ->schema([FlexTextInput::make('title')]);
```

This preset enables inherited live binding with a 250 ms debounce so badges update as the user types. A template can override that policy with its own `live()`/binding configuration.

#### `activeTabWithValue()`

On mount, selects the first locale tab that has at least one non-empty field. Falls back to tab 1 when all tabs are empty.

```php
TranslatableFields::make('Content')
    ->locales(['ar', 'en'])
    ->activeTabWithValue()
    ->schema([FlexTextInput::make('title')]);
```

### Storage — JSON / array (no Spatie)

Recommended minimum setup. Works out of the box with Filament's nested state merging.

```php
// migration
$table->json('title');
$table->json('body')->nullable();
```

```php
class Post extends Model
{
    protected $fillable = ['title', 'body'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
        ];
    }
}
```

```php
TranslatableFields::make('Content')
    ->locales(['pl' => 'PL', 'en' => 'EN'])
    ->schema([
        FlexTextInput::make('title')->hiddenLabel(),
        FlexTextareaField::make('body')->hiddenLabel(),
    ]);
```

**State shape:**

| Layer | Shape |
|-------|-------|
| Form state paths | `title.pl`, `title.en`, `body.pl`, … |
| Persisted attribute | `{"pl":"Tytuł","en":"Title"}` per column |

Before native Filament hydration, the locale owner prepares JSON/array maps. Explicit empty maps, `null` and cleared locale values remain authoritative. Rendering and badge evaluation never reload a record value over an edit.

### Storage — Spatie `laravel-translatable` (optional)

```bash
composer require spatie/laravel-translatable:^6.0
```

```php
use Spatie\Translatable\HasTranslations;

class Post extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'body'];

    protected $fillable = ['title', 'body'];
}
```

```php
TranslatableFields::make('Content')
    ->spatieTranslatable(true)
    ->locales(['pl' => 'PL', 'en' => 'EN'])
    ->schema([
        FlexTextInput::make('title')->hiddenLabel(),
        FlexTextareaField::make('body')->hiddenLabel(),
    ]);
```

| Behaviour | Detail |
|-----------|--------|
| **Without Spatie installed** | JSON/array mode has no Spatie dependency. Explicit opt-in without the optional package reports an actionable configuration error. |
| **Model contract** | Use `HasTranslations` and declare every mapped attribute in its translatable list (or Spatie's attribute declaration). Misconfigured models report an error. |
| **On edit** | Prepare the full translation map before native field hydration. Existing locale getters are respected; missing locales never acquire fallback text. |
| **On save** | Native field defaults, hydration hooks, state casts, validation and dehydration callbacks remain intact. No implicit trimming or conversion of `false`, `0`, arrays, empty strings or `null` is added. |
| **Unexposed locales** | Spatie's native `setTranslations` merging preserves languages omitted from the configured locale list. |
| **Spatie policies** | Its null/empty-string filtering, setters, translation events and model persistence retain their native behavior. FLEX does not replace the model's save logic. |
| **Nested JSON attributes** | `meta->description` renders at `meta.description.en` in form state and dehydrates to `meta->description => [locale => value]` for native Spatie persistence. |

Spatie remains optional and is not added to the package's required dependencies.
The editor reads stored locale maps; application-facing fallback and translation
query APIs remain the model's responsibility. See the [Spatie v6 documentation](https://spatie.be/docs/laravel-translatable/v6/introduction).

### Advanced customization

#### `localeFieldUsing(Closure $callback, bool $useTemplateStatePath = false)`

Replace the default field-cloning strategy. Return a custom `Field` instance or `null` to fall back to the default clone. Custom instances are cloned for isolation; their own names/paths remain intact by default for compatibility. In that mode the callback owns state mapping and persistence.

Pass `useTemplateStatePath: true` to replace only the renderer: FLEX assigns the
template's automatic locale path and storage binding. This is the recommended mode
when the replacement should participate in standard JSON/Spatie persistence.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\TranslatableTab;
use Filament\Forms\Components\Field;

TranslatableFields::make('Content')
    ->locales(['pl' => 'PL'])
    ->localeFieldUsing(function (FlexTextInput $template, string $locale, TranslatableTab $tab): FlexTextInput {
        return FlexTextInput::make("custom_{$locale}")
            ->label($template->getLabel())
            ->placeholder("Title ({$locale})");
    })
    ->schema([FlexTextInput::make('title')->label('Title')]);
```

Injected closure parameters: `$template`, `$locale`, `$tab`.

#### `storageAttributeUsing(Closure $callback)`

Override the Eloquent attribute used for hydration (default: template field `name`).

```php
TranslatableFields::make('Content')
    ->locales(['en' => 'EN'])
    ->storageAttributeUsing(fn (Field $template, string $locale): string => 'custom_title')
    ->schema([FlexTextInput::make('title')]);
```

Useful when the form field name differs from the database column, or when multiple templates map to custom storage logic.

#### `modifyTabsUsing(Closure $closure, bool $merge = true)`

Run callbacks against each `TranslatableTab` after build. `$merge = false` replaces all previous tab modifiers.

```php
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\TranslatableTab;

TranslatableFields::make('Content')
    ->locales(['en' => 'English'])
    ->modifyTabsUsing(function (TranslatableTab $tab, string $locale): void {
        $tab->icon('heroicon-o-language');
    })
    ->schema([FlexTextInput::make('title')]);
```

#### `modifyFieldsUsing(Closure $closure, bool $merge = true)`

Run callbacks against each cloned field. `$merge = false` replaces all previous field modifiers.

```php
TranslatableFields::make('Content')
    ->locales(['pl', 'en'])
    ->modifyFieldsUsing(function (Field $field, string $locale): void {
        $field
            ->placeholder("Enter text ({$locale})")
            ->maxLength(255);
    })
    ->schema([FlexTextInput::make('title')]);
```

Presets are typed options applied once to each locale clone before user modifiers. Repeating a preset replaces its option instead of stacking callbacks. `borderedPanels(false)` reverses a previous `true`; it also accepts an injected closure.

#### Custom tab badges

Beyond the empty-badge preset, `TranslatableTab` (extends `SegmentTab`) supports Filament badge APIs:

```php
TranslatableFields::make('Content')
    ->locales(['en' => 'English'])
    ->modifyTabsUsing(fn (TranslatableTab $tab) => $tab
        ->badge('draft')
        ->badgeColor('gray'))
    ->schema([FlexTextInput::make('title')]);
```

### State paths and nested attributes

`TranslatableAttributePath` resolves form paths from template field names and optional custom `statePath()`:

| Template | Locale | Form state path |
|----------|--------|-----------------|
| `FlexTextInput::make('title')` | `en` | `title.en` |
| `FlexTextInput::make('title')-&gt;statePath('metadata.title')` | `en` | `metadata.title.en` |

Storage attribute for hydration defaults to the field `name` (`title`), not the full state path.

### Custom configuration API

#### `schema(array|Closure $fields)`


One or more `Field` instances used as templates. Only `Field` subclasses are supported — other schema components will throw at build time.

```php
TranslatableFields::make('field_name')
    ->schema([
        // ... schema components
    ]);
```
#### `locales(array|Closure $locales)`


Locale codes as a list (`['ar', 'en']`) or map (`['ar' =&gt; 'Arabic', 'en' =&gt; 'English']`).

```php
TranslatableFields::make('field_name')
    ->locales(['pl', 'en', 'de']);
```
#### `localesLabels(array|Closure $localeLabels)`


Labels keyed by locale code. Used when `locales()` is a plain list.

```php
TranslatableFields::make('field_name')
    ->localesLabels([
        'pl' => 'Polski',
        'en' => 'English',
        'de' => 'Deutsch',
    ]);
```
#### `spatieTranslatable(bool|Closure $condition = true)`


Marks fields for Spatie-aware dehydration and documents intent. Hydration auto-detects `HasTranslations` on the record when the package is installed.

```php
TranslatableFields::make('field_name')
    ->spatieTranslatable(true);
```
#### `localeFieldUsing(Closure $callback, bool $useTemplateStatePath = false)` / `storageAttributeUsing(Closure $callback)`


See [Advanced customization](#advanced-customization).

```php
TranslatableFields::make('field_name')
    ->localeFieldUsing()
    ->storageAttributeUsing();
```
#### `modifyTabsUsing(Closure $closure, bool $merge = true)` / `modifyFieldsUsing(Closure $closure, bool $merge = true)`


See [Advanced customization](#advanced-customization).

```php
TranslatableFields::make('field_name')
    ->modifyTabsUsing(true)
    ->modifyFieldsUsing(true);
```
#### `directionByLocale(TranslatableDirectionScope $scope = Auto)` / `emptyBadgeWhenAllFieldsAreEmpty(?string $emptyLabel = null)` / `activeTabWithValue()` / `withRecommendedDefaults(?string $emptyBadgeLabel = null)` / `borderedPanels(bool $condition = true)`


See [Production presets](#production-presets).

```php
TranslatableFields::make('field_name')
    ->directionByLocale()
    ->emptyBadgeWhenAllFieldsAreEmpty('Brak danych')
    ->activeTabWithValue()
    ->withRecommendedDefaults('Brak danych')
    ->borderedPanels(true);
```

### Inherited SegmentTabs API

`TranslatableFields` extends `SegmentTabs`. Defaults differ: separators are **off** (`separators(false)` in `setUp()`), CSS class `fff-translatable-fields` is applied, and tab panels are **flat** (no border/padding). Use `borderedPanels()` for a card-style panel. Multiple fields in one tab get vertical spacing only (`mt-4` between field wrappers).

| Method | Description |
|--------|-------------|
| `activeTab(int\|Closure $activeTab)` | 1-based index of the selected tab. `activeTabWithValue()` sets a dynamic closure. |
| `persistTabInQueryString(string\|Closure\|null $key = 'segment-tab')` | Persist selected tab in the URL query string. |
| `variant(string\|Closure $variant)` | `default` (filled track) or `ghost`. **Default:** `default`. |
| `color(string\|Closure\|null $color)` | Selection accent; ghost variant defaults to `primary`. |
| `separators(bool\|Closure $condition = true)` | Vertical dividers between segments. **Default for TranslatableFields:** `false`. |
| `fullWidth(bool\|Closure $condition = true)` | Stretch tabs to full container width. |
| `iconOnly(bool\|Closure $condition = true)` | Hide tab labels; show icons only. |
| `expandSelectedLabel(bool\|Closure $condition = true)` | Animate selected tab to wider width. |
| `helperText(string\|Htmlable\|Closure\|null $text)` | Helper copy below the tab panels (field-style). |
| `size(string\|ControlSize\|Closure $size)` | See [Control size](/docs/configuration). |

### Global defaults

#### `TranslatableFields::configureUsing()`

Filament-native global defaults (same pattern as other Filament components):

```php
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;

TranslatableFields::configureUsing(function (TranslatableFields $component): void {
    $component
        ->locales(['ar', 'en'])
        ->localesLabels(['ar' => 'Arabic', 'en' => 'English'])
        ->withRecommendedDefaults(__('locales.empty'))
        ->separators(true);
});
```

Register in a service provider `boot()` method. Every `TranslatableFields::make()` (including macro-created instances) receives these defaults.

#### Config file

```php
// config/filament-flex-fields.php
'translatable' => [
    'locales' => ['ar' => 'Arabic', 'en' => 'English'], // or ['ar', 'en']
    'locale_labels' => ['ar' => 'Arabic', 'en' => 'English'],
    'empty_badge_label' => 'empty',
    'rtl_locales' => ['ar', 'he', 'fa', 'ur'],
],
```

| Config key | Used by |
|------------|---------|
| `translatable.locales` | Default locales when `-&gt;locales()` is omitted |
| `translatable.locale_labels` | Tab labels for list-style locale codes |
| `translatable.empty_badge_label` | `emptyBadgeWhenAllFieldsAreEmpty()` default label |
| `translatable.rtl_locales` | `directionByLocale()` RTL detection |
| `slug.translatable_locales` | Fallback when `translatable.locales` is `null` |

### Architecture overview

Internal services keep the component thin and testable:

| Class / concern | Responsibility |
|-----------------|----------------|
| `TranslatableAttributePath` | Resolves relative state paths (`metadata.title.en`) and storage attribute names |
| `TranslatableHydrator` | Hydrates locale fields from JSON columns or Spatie `getTranslation()` |
| `TranslatableFieldFactory` | Clones template fields into per-locale instances (or delegates to `localeFieldUsing`) |
| `TranslatableTabFactory` | Builds `TranslatableTab` instances from locale list + templates |
| `TranslatableTabState` | Evaluates tab field values for empty badges and active-tab selection |
| `TranslatableLocales` | Resolves locale codes and labels from inline config or `config()` |
| `SpatieTranslatableIntegration` | Detects `HasTranslations` on the edited record |
| `TranslatableFieldBuilder` | Backwards-compatible facade delegating to the services above |
| `TranslatableFields` concerns | Locales, schema templates, modifiers, presets, migration aliases |

Component concerns (under `TranslatableFields/Concerns/`):

| Concern | Methods |
|---------|---------|
| `ConfiguresTranslatableLocales` | `locales()`, `localesLabels()`, `getLocales()` |
| `CustomizesTranslatableComponents` | `schema()`, `modifyTabsUsing()`, `modifyFieldsUsing()`, `spatieTranslatable()`, `localeFieldUsing()`, `storageAttributeUsing()` |
| `BuildsTranslatableTabs` | `buildTranslatableTabs()`, child-schema modifier application |
| `ProvidesTranslatablePresets` | `directionByLocale(TranslatableDirectionScope)`, `emptyBadgeWhenAllFieldsAreEmpty()`, `activeTabWithValue()`, `withRecommendedDefaults()`, `borderedPanels()` |
| `HasTranslatableMigrationAliases` | `addDirectionByLocale()`, `addEmptyBadgeWhenAllFieldsAreEmpty()`, `addSetActiveTabThatHasValue()` |

### Relationship to TitleSlugField

| | `TranslatableFields` | `TitleSlugField` translatable titles |
|---|---------------------|--------------------------------------|
| **Purpose** | Any translatable attribute(s) | Title + single shared slug |
| **Slug** | Not involved | One slug; only `slugSourceLocale` drives auto-sync |
| **Component** | Standalone schema / field macro | `FusedGroup` factory; title tabs use `TranslatableFields` |
| **Locale config** | `translatable.*` config + `-&gt;locales()` | `slug.translatable_locales` + `translatableLocales` param |
| **Shared internals** | Full architecture above | Same `TranslatableFields` + `TranslatableHydrator` for title tabs |
| **Extra config** | Direct fluent API | `translatableFieldsConfigurator` + `titleLocaleConfigurator` |
| **Empty tab badges** | Opt-in via `emptyBadgeWhenAllFieldsAreEmpty()` | **On by default** for title locale tabs |
| **Default active tab** | `activeTabWithValue()` in `withRecommendedDefaults()` | `slugSourceLocale` tab |

For translatable titles with permalink generation, see [Translatable titles (single slug)](/docs/slugfield-and-titleslugfield#translatable-titles-single-slug).

### Playground

Slug: **`translatable-fields`** — `/admin/flex-fields/translatable-fields`

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [SegmentTabs](/docs/segmenttabs) | Generic segmented tabs without locale cloning |
| [TitleSlugField](/docs/slugfield-and-titleslugfield) | Translatable titles with one shared slug |
| [FlexTextInput](/docs/flextextinput) | Single-locale text without JSON storage |
| [TranslatableFields macro](/docs/translatablefields#basic-usage-—-field-macro) | Same layout via `Field::translatableFields()` |

---

# Part III — Appendix

## Shared locale tabs

Locale tabs reuse SegmentTabs and its Control renderer, CSS and runtime. Each locale
schema is cloned and modified once per instance. Changing templates/locales/modifiers
invalidates that cache; cloning the wrapper creates an independent cache. Actual
child fields declare their own assets, so the wrapper does not preload every
possible input type. Multiple locale groups have independent selection and unique
panel IDs, including inside nested Filament action modals.

### Locale keys and lifecycle contract

Locale keys retain their exact spelling, including `pt_BR` and `zh-Hant`, so they
match existing Spatie data. Duplicate keys and invalid dot-path codes are rejected;
`localesLabels()` can override labels for both lists and locale-to-label maps.

Schemas and injected locale/storage callbacks resolve in their actual form context.
Locale templates are isolated clones. The owner caches one composed schema per
instance, clears it after configuration changes or cloning, and discards a partly
configured cache when a modifier fails. Defaults and all native callbacks run through
Filament; the translation adapter never replaces them. Full and partial fills use
the same preparation rules, including inside repeater items.

The wrapper shares SegmentTabs' renderer and runtime. Each actual child retains
its own CSS/JS consumer boundary; configuring many locales does not eagerly activate
fields in inactive tabs. Code examples remain separate files, read only by the
catalog's Code Example surface.

Behavior changes in the rebuild: locale keys are no longer lowercased, explicit
Spatie opt-in validates its optional dependency/model contract, and strings are no
longer trimmed or converted to null by FLEX. Applications requiring that conversion
can declare it explicitly with the field's `dehydrateStateUsing()` callback.
