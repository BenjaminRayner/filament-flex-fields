---
title: "TagsField"
description: Tag input with pill chips, inline remove buttons, and optional server-side suggestions.
icon: "tags"
"canonical": "https://flex-fields.bjanczak.com/docs/tags-field"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/tags-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "TagsField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/tags-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/tags-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/tags-field.webp"
"twitter:image:alt": "TagsField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/tags-field#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/tags-field",
      "name": "TagsField",
      "description": "Tag input with pill chips, inline remove buttons, and optional server-side suggestions.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/tags-field#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/tags-field#article",
      "url": "https://flex-fields.bjanczak.com/docs/tags-field",
      "headline": "TagsField",
      "description": "Tag input with pill chips, inline remove buttons, and optional server-side suggestions.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/tags-field#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/tags-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/tags-field-motion.mp4#video",
      "name": "TagsField — FLEX Fields demonstration",
      "description": "A recorded demonstration of TagsField in the FLEX Fields playground for Filament. Tag input with pill chips, inline remove buttons, and optional server-side suggestions.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/tags-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.533S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/tags-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/tags-field#article"
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
  src="/art/previews/tags-field-motion.mp4"
  poster="/art/previews/tags-field.webp"
  width="1920"
  height="1080"
  aria-label="TagsField — FLEX Fields demonstration"
>
  <a href="/art/previews/tags-field-motion.mp4">Watch the TagsField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Tag input featuring removable pill chips, optional static or server-side suggestions, and duplicate-insensitive matching. Extends Filament's standard **TagsInput** with FlexTextInput styling, size/variant tokens, and optional Spatie Tags integration.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField` |
| **Spatie class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieTagsField` |
| **State type** | `list<string>` (array) or `string` (delimited) |
| **FieldType** | `tags` |
| **Playground** | `tags-field` slug in Flex Fields playground |

---

### Basic usage

#### Standard Skills Input
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;

TagsField::make('skills')
    ->label('Skills')
    ->suggestions(['PHP', 'Laravel', 'Filament', 'Livewire'])
    ->maxTags(10)
    ->required();
```

#### Comma-Separated Labels
```php
TagsField::make('labels')
    ->separator(',')
    ->placeholder('Add labels...')
    ->variant('secondary')
    ->size('sm');
```

---

### State & validation

#### Stored value
By default, state is an ordered list of trimmed strings (JSON array). If `separator()` is set, it dehydrates as a delimited string.

```php
$record->skills; // ['laravel', 'filament']
$record->labels; // 'php,pest,livewire' (with separator)
```

#### Validation rules (built-in)
| Behaviour | Detail |
|-----------|--------|
| `required()` | At least one tag must be present |
| `maxTags()` | Caps the total number of tags |
| `suggestionsOnly()` | Restricts input to suggestion values only |
| `duplicateInsensitive()` | Case-insensitive duplicate check |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `variant(string $variant)` | Setup | `primary` | `primary`, `secondary`, `flat`, `soft` |
| `size(string $size)` | Setup | `'md'` | `sm`, `md`, `lg` |
| `maxTags(int $max)` | Setup | `null` | Maximum number of tags |
| `suggestionsOnly(bool $cond)` | Setup | `false` | Restrict to suggestions only |
| `duplicateInsensitive(bool $c)` | Setup | `false` | Case-insensitive duplicates |
| `showTagCount(bool $cond)` | Setup | `false` | Show live counter below field |
| `getSearchResultsUsing(Closure $cb)`| Setup | — | Async suggestion search |
| `minSearchLength(int $len)` | Setup | `2` | Min chars for server search |
| `type(string $type)` | Spatie | — | Spatie tag type (Spatie class only) |

---

### Real-world examples

#### Server-Side Suggestion Search
```php
TagsField::make('technologies')
    ->getSearchResultsUsing(function (string $search): array {
        return Technology::query()
            ->where('name', 'like', "%{$search}%")
            ->limit(20)
            ->pluck('name')
            ->all();
    });
```

#### Spatie Tags Integration
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieTagsField;

FlexSpatieTagsField::make('tags')
    ->type('categories')
    ->tagLocale('pl')
    ->tagFallbackLocale('en')
    ->tagSearchLimit(20)
    ->maxTags(20)
    ->label('Categories');
```

---

`FlexSpatieTagsField` is opt-in and keeps the same FLEX Tags renderer, search
runtime and per-instance assets. The application installs `spatie/laravel-tags`,
its migrations and `HasTags` on the model. Plain `TagsField` remains independent
of the optional package. `tagLocale()` selects the locale for searching, hydration
and synchronization without changing application locale. Searches query the
localized JSON name and return model translations, not raw JSON. Typed saving
reuses existing tags in that locale. `tagSearchLimit()` accepts 1–100 (default 20).

`modifyTagSearchQueryUsing(fn ($query, $search, $locale) => $query->where(...))`
can narrow suggestions; it may return the builder or mutate it in place. This is
not a write authorization hook: put tenant ownership/global scopes and creation
rules in the configured Tag model for every read/write path. `type(null)` retains
the documented any-type synchronization semantics; use a named type when domains
must remain separate. The shared endpoint validates input and uses the Select
search rate limiter.

For data-driven field definitions, opt in with `use_spatie_tags: true` and configure
`spatie_tag_type`, `spatie_tag_locale`, `spatie_tag_fallback_locale`,
`spatie_tag_search_limit` and the shared `max_tags` options. Plain tag definitions
ignore Spatie-specific settings; they do not acquire an optional dependency.

`tagFallbackLocale('en')` optionally includes names from that language when the
requested translation is missing. Saving such a label reuses the existing Tag
ID, including an existing owned association; it does not create a new tag per
language or write a fallback translation. Unconfigured fallback retains Spatie's
normal display fallback. New tags use `tagLocale()`. Creation and relation sync
run in one transaction on the record's connection; a typed field preserves other
tag types. Custom Tag models and pivot configuration remain owned by Spatie.
Use the same DB connection for the record, tags and pivot for atomicity.

The field edits tag selections, not translations of the tag catalog. Translate
an existing tag with Spatie's `setTranslation('name', $locale, $name)->save()`;
its existing ID remains the identity shared by all selected languages. Place
`FlexSpatieTagsField` outside language tabs. Per-file upload metadata uses shared
JSON tag strings and does not implicitly attach the parent model's tag relation.

The host integration was verified with installed `spatie/laravel-tags` 4.11.0
and applied `tags` / `taggables` migrations. Applications opting into the field
still need `HasTags` on the model; the package does not modify application models.
The plain field remains usable without Spatie installed. Required fields, maximum
counts, selected tag types, localized suggestions, custom query scopes and
create/edit persistence use the same FLEX renderer and validation contracts.

### Playground

`/admin/flex-fields/tags-field`

See [Playground](/docs/index#playground) for setup.

---

### Ops notes

Async tag suggestion search (`getTagSearchResults`) shares the Select-family Livewire rate limit (`filament-flex-fields.select.search_rate_limit_per_minute`). Keys use the authenticated user id or `Request::ip()` — configure Laravel **TrustedProxies** behind a reverse proxy. Exceeding the limit returns an empty suggestion list (no exception). Client search also uses `searchRequestId` + `AbortController` so stale responses never overwrite newer typing.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [SelectField](/docs/selectfield) | Multi-select with fixed options |
| [FlexChecklist](/docs/flexchecklist) | Grid of checkbox choices |
| [UserSelect](/docs/userselect) | Specifically for selecting users |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-tags-field` | Root field wrapper |
| `fff-tags-field--{sm\|md\|lg}` | Size variant |
| `fff-tags-field--{variant}` | Visual variant |
| `fff-flex-text-input` | Shared input shell |
| `fi-color-{color}` | Pill color modifier |
| `has-focus-outline` | Focus ring active |
