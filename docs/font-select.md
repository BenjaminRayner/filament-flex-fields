---
title: Font Select
icon: "/art/icons/type-outline.svg"
description: Search Google Fonts with real previews, infinite pagination and the shared FLEX Select virtualized dropdown.
"canonical": "https://flex-fields.bjanczak.com/docs/font-select"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/font-select.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Font Select in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/font-select-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/font-select-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/font-select.webp"
"twitter:image:alt": "Font Select in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/font-select#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/font-select",
      "name": "Font Select",
      "description": "Search Google Fonts with real previews, infinite pagination and the shared FLEX Select virtualized dropdown.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/font-select#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/font-select#article",
      "url": "https://flex-fields.bjanczak.com/docs/font-select",
      "headline": "Font Select",
      "description": "Search Google Fonts with real previews, infinite pagination and the shared FLEX Select virtualized dropdown.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/font-select#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/font-select-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/font-select-motion.mp4#video",
      "name": "Font Select — FLEX Fields demonstration",
      "description": "A recorded demonstration of Font Select in the FLEX Fields playground for Filament. Search Google Fonts with real previews, infinite pagination and the shared FLEX Select virtualized dropdown.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/font-select.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT11.933S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/font-select-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/font-select#article"
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
  src="/art/previews/font-select-motion.mp4"
  poster="/art/previews/font-select.webp"
  width="1920"
  height="1080"
  aria-label="Font Select — FLEX Fields demonstration"
>
  <a href="/art/previews/font-select-motion.mp4">Watch the Font Select demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`FontSelect` stores a stable family identifier such as `google:inter`. Its dropdown uses the same selection, keyboard, mobile sheet and virtualization engine as [FLEX Select](/docs/selectfield).

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;

FontSelect::make('heading_font')
    ->default('google:inter')
    ->required();
```

## Real previews without loading the entire catalog

The package includes a local metadata snapshot of Google Fonts. Rendering a form does not contact Google or download the entire font catalog.

Opening the menu loads a bounded metadata page through Livewire. Search runs on the server against the indexed package snapshot, with a 120 ms debounce; it does not call the Google Fonts Developer API for each query. This avoids an extra provider round trip, API credentials and provider availability in the search path. TanStack virtualization mounts only a small window of rows; **only rows intersecting the visible viewport request font previews**. Overscan rows do not request fonts. Infinite scrolling appends metadata pages, not font files for every appended family.

Previews use Google Fonts CSS2 with `text=` to request the sample's glyphs. The manager fetches and decodes the font under a private family alias, then reveals the sample. A fixed-height skeleton keeps each compact, single-line row stable while loading. Failed previews retain a readable family name and offer **Retry font previews**.

There are at most four concurrent preview loads across all Font Select instances in one document. Selected fonts take priority. During fast scrolling, departed previews are released immediately and new previews wait for a short 60 ms scroll intent window; scrolling itself does not wait for font loading.

By default, active selections retain their previews after the menu closes. Use `previewSelectedFont(false)` for dropdown-only previews. Inactive tabs and closed modals release their leases; suspended parent modals retain theirs. Shared previews use one decoded resource until their last owner releases it. The manager deletes only the `FontFace` objects it created. It never removes application stylesheets, panel fonts or third-party fonts.

<Note>
These are **preview subsets**, not a full font installation for your application's content. Save the family ID, then load the full font and required weights through your application's font pipeline. Removing a managed FontFace does not clear the browser's HTTP cache or guarantee immediate memory reclamation.
</Note>

## Restrict the available fonts

```php
FontSelect::make('brand_font')
    ->fonts(['google:inter', 'google:roboto', 'google:lora'])
    ->exceptFonts(['google:roboto']);

FontSelect::make('editorial_font')
    ->categories(['serif'])
    ->subsets(['latin-ext'])
    ->variableOnly()
    ->sortFonts('alpha');
```

IDs are provider-prefixed, lowercase catalog slugs. The PHP catalog is available through `GoogleFonts::families()` from `Bjanczak\FilamentFlexFields\Support\Fonts\Providers`. Use its record keys instead of generating slugs from display labels. Subset filters match families supporting at least one requested subset.

Server validation rejects arrays, labels, resource URLs, unknown IDs and IDs outside the field's current policy. An optional field accepts an empty value; `required()` rejects it. The database value contains only the family ID, not a preview descriptor or label.

## Preview text and variants

```php
FontSelect::make('brand_font')
    ->previewText('Your brand, beautifully set.')
    ->previewWeight(600)
    ->previewStyle('normal');
```

The default sample is the family name. An emoji-only family uses an emoji sample. Custom text accepts 1–128 printable characters. Weight is 1–1000; style is `normal` or `italic`. Variable families with a `wght` axis use the exact requested weight within their supported range. When a requested static variant is unavailable, the picker uses the nearest available weight in that style, falling back to an available style when necessary. Each compact 36 px row displays only the family name, at 14 px, in its actual font. Options have a 2 px gap; the virtual window uses the matching 38 px stride. Custom text configures the downloaded preview glyph subset; family-name glyphs are always included so the row and selected value remain correct.

## Keep the normal input font

```php
FontSelect::make('heading_font')
    ->previewSelectedFont(false)
    ->default('google:inter');
```

With `previewSelectedFont(false)`, the selected family name inherits the normal input typography. The picker does not request or retain a font merely because it is selected, including after a reload and for read-only fields. Real previews still load for visible dropdown rows, and their leases are released when the menu closes. Other active pickers can independently retain the same resource.

The default is `true`, preserving selected previews in existing forms. The method also accepts a closure, evaluated by Filament. This setting changes presentation and preview loading only: the database still stores the same family ID, and catalog filters and validation remain in effect. All playground examples use `false`.

## Category tabs and pagination

```php
FontSelect::make('font')
    ->categoryTabs(['sans-serif', 'serif', 'monospace'])
    ->categoryLabels(['sans-serif' => 'Sans serif', 'monospace' => 'Code'])
    ->perPage(48);

FontSelect::make('font')->categoryTabs(false);
```

Default tabs are All, Serif, Sans, Mono, Display and Hand. The active tab uses your Filament primary palette. The category strip reaches both panel edges and sits below search with a small separation. Tabs scroll horizontally when space is limited. Their initial internal padding aligns the first tab with search, and scrolls away with the content instead of leaving a fixed blank strip. Category IDs are `serif`, `sans-serif`, `monospace`, `display` and `handwriting`. Tab selection filters within the server policy; it never expands the permitted catalog. Labels use package translations and can be overridden per instance.

Page size is 1–96, default 48. Continuation cursors are signed and bound to the query, category, catalog revision and field policy. Stale search responses cannot replace a newer query's results.

## Local fonts

```php
FontSelect::make('brand_font')
    ->providers(['local'])
    ->localFonts([
        'brand' => [
            'family' => 'Brand Sans',
            'category' => 'sans-serif',
            'variants' => ['400'],
            'url' => '/fonts/brand-regular.woff2',
        ],
    ]);
```

This stores `local:brand`. Use `providers(['google', 'local'])` to include both catalogs. Local files must be same-origin WOFF, WOFF2, TTF or OTF files, with no URL credentials or fragments. Each local entry describes one file; declare only the weights/styles that file actually supports. Catalogs are limited to 1,000 local entries. Each preview is capped at 512 KiB and has a 15-second timeout. Host appropriately licensed font files yourself.

## Appearance, state and accessibility

```php
FontSelect::make('font')->size('sm')->variant('secondary');
FontSelect::make('font')->size('lg')->rounding('xl');
FontSelect::make('font')->readOnly()->default('google:inter');
FontSelect::make('font')->disabled()->default('google:inter');
FontSelect::make('font')->clearable(false)->placeholder('Choose brand typography');
```

Sizes are `sm`, `md`, `lg`; input appearance belongs to FLEX Select. The default variant is `bordered`; `secondary`, `flat`, `faded`, `soft`, `underlined` and `item-card` reuse Select presentation. Rounding options are `md`, `lg`, `xl`, `native`, `full`. RTL, keyboard navigation, search, clearing, viewport positioning and the mobile sheet follow the shared Select. Font Select currently supports a **single family selection**. Do not configure native HTML mode, custom Select options, relationship options or multiple selection for this adapter.

The dropdown's scroll fades grow continuously with distance from its top and bottom edges. The scrollbar sits outside the masked content. Reduced-motion preferences disable skeleton pulsing and preview transitions.

## JSON custom fields

Use `font_select` as the field type. Configuration keys map directly to the PHP API:

```json
{
  "providers": ["google"],
  "fonts": ["google:inter", "google:lora"],
  "category_tabs": ["sans-serif", "serif"],
  "preview_text": "Brand typography",
  "preview_weight": 400,
  "preview_style": "normal",
  "preview_selected_font": false,
  "per_page": 48,
  "sort_fonts": "popularity",
  "size": "md",
  "variant": "bordered"
}
```

Additional keys are `except_fonts`, `categories`, `subsets`, `local_fonts`, `category_labels`, `variable_only` and `rounding`. `fonts: null` permits all families matching the remaining policy. Omit optional keys to use defaults. Invalid shapes and out-of-range values are rejected before constructing the field.

## Use the saved font on a website or in Filament

The saved value is a reusable ID, not a temporary preview URL. `previewWeight()` and `previewStyle()` configure picker presentation; they do not add weight or italic to the stored ID. If users choose typography settings, persist those separately and validate them against the resolved family. Resolve it against the local catalog without contacting Google:

```php
use Bjanczak\FilamentFlexFields\Support\Fonts\FontCatalog;

// $settings is your application's cached database settings record.
$font = FontCatalog::find($settings->font_id)
    ?? FontCatalog::find('google:inter');

$font->family;       // Inter
$font->category;     // sans-serif
$font->variants;     // Available weights and italic variants
$font->variable;     // Whether the family has variable axes
$font->axes;         // Axis tags and supported min/max/default values
```

`find()` accepts `null` and returns `null` for malformed or unknown IDs. An application can choose its own fallback. Google lookup is an indexed lookup in the shared metadata snapshot. For local IDs, supply the same trusted configuration through `FontCatalog::find($id, localFonts: $configuredFonts)`.

### Load a full font in a Blade template

```blade
<link rel="stylesheet" href="{{ $font->stylesheetUrl(weights: [400, 600, 700]) }}">

<main style="font-family: {{ $font->cssFamily() }}, sans-serif">
    Your website content
</main>
```

`stylesheetUrl()` returns a complete Google CSS2 stylesheet URL **without `text=`**. This is appropriate for arbitrary application text. It requests only your chosen weights and styles, uses exact supported variable weights and maps unavailable static weights to available variants, deduplicates them, and orders the CSS2 axes correctly:

```php
$font->stylesheetUrl(
    weights: [400, 700],
    styles: ['normal', 'italic'],
    display: 'swap',
);
```

Weights accept 1–12 integers in the 1–1000 range; styles are `normal` and `italic`. Display modes are `auto`, `block`, `swap`, `fallback`, `optional`. The default requests normal 400, 500, 600 and 700 with `swap`.

For an actual variable weight range, use the dedicated helper. It validates the requested range against the family's `wght` axis and rejects unavailable styles:

```php
$font = FontCatalog::find('google:inter');
$font->stylesheetUrl(weights: [450, 550], styles: ['normal', 'italic']);
$font->variableStylesheetUrl(
    weightRange: [100, 900],
    styles: ['normal', 'italic'],
);
// Use font-weight: 550 in your application CSS with the complete variable font.
```

The default range is 100–900; pass the family's supported limits for families with a narrower or wider axis. Preview requests still use a single weight and `text=`; requesting a full variable range for your application does not enlarge picker previews. Other supported axis ranges are available in `$font->axes` for your own font pipeline; this helper requests weight and italic, not arbitrary custom axes.

For a local font, `stylesheetUrl()` returns `null`. Generate its declarations in your own CSS asset or trusted template instead:

```blade
<style>
{!! $font->fontFaceCss(display: 'swap') !!}
</style>
<main style="font-family: {{ $font->cssFamily() }}, sans-serif">…</main>
```

`fontFaceCss()` declares the configured local variants and safely quotes the family and source URL. It returns an empty string for Google families. `cssFamily()` returns a quoted CSS family name suitable for the Blade attribute shown above.

### Use a selected Google family as the Filament panel font

In your `PanelProvider`, read a cached database setting and explicitly use Filament's Google provider:

```php
use Bjanczak\FilamentFlexFields\Support\Fonts\FontCatalog;
use Filament\FontProviders\GoogleFontProvider;

$font = FontCatalog::find($settings->panel_font_id)
    ?? FontCatalog::find('google:inter');

return $panel->font(
    $font->family,
    url: $font->stylesheetUrl(weights: [400, 500, 600, 700]),
    provider: GoogleFontProvider::class,
);
```

The picker chooses and validates the ID; your settings form persists it. Your application's settings service supplies the cached value when the panel is resolved. Avoid querying the settings table separately for every field or on every preview request. A saved panel-font change takes effect when the panel is next loaded; the picker does not silently change global panel typography while browsing the menu.

For a local panel font, put `fontFaceCss()` in your own stylesheet and pass that stylesheet URL with `Filament\FontProviders\LocalFontProvider::class`. Application stylesheets and full fonts remain application-owned and are never removed when the picker or modal closes.

## Selected preview after a reload

The server renders the selected family name immediately. With the default `previewSelectedFont(true)`, the preview manager then requests that selection with priority, using a private preview alias. The browser cache can make repeat loads fast, but a fresh browser or slow network still needs a download and decode. The actual face is applied only after it is ready; selected preview does not require opening the dropdown.

With `previewSelectedFont(false)`, the name keeps the normal input font from the first render onward. No selected-font download or later font replacement is needed. Dropdown previews remain available when the menu opens.

The picker previews a small subset. Loading full application typography through the helpers above is a separate explicit step, so preview cleanup cannot remove your website or panel font.

## Deployment and provider access

Publish assets using the [installation instructions](/docs/installation). No Google API key is required to use the bundled catalog. Google previews require browser access to `https://fonts.googleapis.com` and `https://fonts.gstatic.com`. Where your CSP or privacy policy blocks those hosts, restrict the picker to local fonts. Browser font decoding requires `FontFace` support.

The metadata snapshot is versioned with the package. Maintainers can refresh it with `python3 scripts/fonts/import-google-fonts.py`; the importer validates and records source provenance. It is a maintenance tool, not a request performed by customer pages. Previews are bounded and cached by the browser, but network availability and provider response times remain external dependencies.
