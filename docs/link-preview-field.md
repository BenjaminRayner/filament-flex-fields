---
title: "Link Preview Field"
description: "A Filament URL field with Open Graph previews, shared FLEX inputs, cached metadata and protected image delivery."
icon: "link"
"canonical": "https://flex-fields.bjanczak.com/docs/link-preview-field"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/link-preview-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Link Preview Field in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/link-preview-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/link-preview-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/link-preview-field.webp"
"twitter:image:alt": "Link Preview Field in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/link-preview-field#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/link-preview-field",
      "name": "Link Preview Field",
      "description": "A Filament URL field with Open Graph previews, shared FLEX inputs, cached metadata and protected image delivery.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/link-preview-field#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/link-preview-field#article",
      "url": "https://flex-fields.bjanczak.com/docs/link-preview-field",
      "headline": "Link Preview Field",
      "description": "A Filament URL field with Open Graph previews, shared FLEX inputs, cached metadata and protected image delivery.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/link-preview-field#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/link-preview-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/link-preview-field-motion.mp4#video",
      "name": "Link Preview Field — FLEX Fields demonstration",
      "description": "A recorded demonstration of Link Preview Field in the FLEX Fields playground for Filament. A Filament URL field with Open Graph previews, shared FLEX inputs, cached metadata and protected image delivery.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/link-preview-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.033S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/link-preview-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/link-preview-field#article"
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
  src="/art/previews/link-preview-field-motion.mp4"
  poster="/art/previews/link-preview-field.webp"
  width="1920"
  height="1080"
  aria-label="Link Preview Field — FLEX Fields demonstration"
>
  <a href="/art/previews/link-preview-field-motion.mp4">Watch the Link Preview Field demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Enter a URL and show its page title, description and image without leaving your form.
Link Preview uses the same input shell, sizes and appearance as [Flex Text Input](/docs/flextextinput).
The database stores the URL, not downloaded HTML or preview images.

<CardGroup cols={2}>
  <Card title="Three preview layouts" icon="rectangle-list">Choose a compact horizontal preview, a vertical preview or a full-width image card.</Card>
  <Card title="Responsive loading" icon="bolt">Requests run after input changes. Cached previews can appear on the first render without a remote request.</Card>
</CardGroup>

## First field

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\LinkPreviewField;

LinkPreviewField::make('website')
    ->label('Website')
    ->required();
```

Publish the package assets as described in [Installation](/docs/installation).
Authenticated routes are registered automatically from the component definition.
No queue worker or additional frontend build is required for previews.

## Layout and appearance

<Tabs>
  <Tab title="Horizontal">
```php
LinkPreviewField::make('article_url')
    ->previewLayout('horizontal')
    ->size('md');
```
The image sits beside the title, description and domain.
  </Tab>
  <Tab title="Vertical">
```php
LinkPreviewField::make('reference_url')
    ->previewLayout('vertical')
    ->variant('secondary');
```
A compact image card shows the title and domain beneath the image.
  </Tab>
  <Tab title="Image card">
```php
LinkPreviewField::make('campaign_url')
    ->previewLayout('card')
    ->columnSpanFull();
```
The image spans the preview width; the title and domain follow below it.
  </Tab>
</Tabs>

## Every field option

Fluent options accept closures for values that depend on the record or form.

| Method | Default | Behavior |
| --- | --- | --- |
| `variant()` | `primary` | `primary`, `secondary`, `soft`, `flat`, or `ghost`; shared input appearance. |
| `size()` | `md` | `sm`, `md`, `lg`, or a `ControlSize` enum. |
| `rounding()` | Global field setting | Shared FLEX input rounding token. See [Configuration](/docs/configuration). |
| `focusOutline()` | `true` | Shows the shared Flex Text Input outline around the whole input on focus. Use `focusOutline(false)` to disable it. |
| `placeholder()` | Translated URL prompt | Placeholder for the editable portion of the URL. |
| `prefix()` | `null` | Visible URL prefix; added to the entered value and stored as part of the URL. |
| `suffix()` | `null` | Visual suffix label; it is **not** appended to the stored URL. |
| `preview()` | `true` | `false` disables the card and all preview requests for this instance. URL validation remains active. |
| `previewLayout()` | `horizontal` | `horizontal`, `vertical`, or `card`. |
| `previewDebounce()` | `500` | Wait after typing, in milliseconds; `0`–`60000`. |
| `previewMinUrlLength()` | `10` | Minimum full URL length before fetching; `4`–`2048`. |
| `previewMinSkeletonMs()` | `500` | Minimum loading presentation for a new successful fetch; `0`–`5000`. Cached previews skip this delay. |
| `resolveInitialPreviewOnServer()` | `true` | Reads **existing cached metadata only** during rendering. It never performs DNS or HTTP requests. `false` leaves the initial lookup to the browser. |
| `showVisitLink()` | `true` | Makes the domain a link opening in a new tab. `false` shows plain text. |
| `visitLabel()` | Translated “Visit” label | Accessible label of the domain link. |
| `visitIcon()` | `FlexIcon::Paperclip` | Filament-compatible icon name, enum, HTML or `null`. |
| `readOnly()` | `false` | Prevents editing; permits the current URL's preview. |
| `disabled()` | `false` | Prevents editing and new metadata requests. Existing cached previews may still display and load their image. Native Filament disabled/dehydration behavior applies. |

Standard Filament methods such as `label()`, `helperText()`, `required()`,
`live()`, `afterStateUpdated()` and `columnSpanFull()` remain available.

## Practical examples

### Enter only a path

```php
LinkPreviewField::make('campaign_url')
    ->prefix('https://example.com/campaigns/')
    ->placeholder('summer-sale')
    ->helperText('Enter the campaign path. The full URL is saved.');
```

Pasting a full matching URL removes the duplicated visible prefix.

### Smaller input, quieter preview

```php
LinkPreviewField::make('reference_url')
    ->size('sm')
    ->variant('soft')
    ->previewDebounce(800)
    ->previewMinSkeletonMs(300)
    ->showVisitLink(false);
```

### URL input without remote requests

```php
LinkPreviewField::make('internal_url')
    ->preview(false)
    ->placeholder('https://intranet.example.com/help');
```

A valid private URL may be stored. Private hosts cannot be fetched for previews.

### Read-only record

```php
LinkPreviewField::make('submitted_url')
    ->readOnly()
    ->previewLayout('vertical')
    ->visitLabel('Open submitted page');
```

### React to URL changes

```php
use Filament\Schemas\Components\Utilities\Set;

LinkPreviewField::make('source_url')
    ->live(debounce: 500)
    ->afterStateUpdated(function (?string $state, Set $set): void {
        $set('source_domain', filled($state)
            ? parse_url($state, PHP_URL_HOST)
            : null);
    });
```

Preview debounce and Livewire update debounce are independent settings.

## Validation and storage

The server validates a nullable string of at most **2048 characters**, with an
HTTP or HTTPS scheme, no embedded credentials, and the default HTTP/HTTPS port.
Arrays, arbitrary text, `javascript:` URLs and unsupported ports are rejected.
`required()` makes empty values invalid. Whitespace is trimmed; empty values save as `null`.

A preview failure does not make an otherwise valid URL unsaveable. Pages may omit
metadata, block automated requests, require a login, or exceed the download limit.

```php
// Migration: use a suitable length for the URLs you accept.
$table->string('website', 2048)->nullable();

// Field: add a shorter application-specific constraint if necessary.
LinkPreviewField::make('website')
    ->required()
    ->rules(['max:500']);
```

No model cast is required. Authorization to edit the record remains your
application's responsibility.

## Global configuration

In `config/filament-flex-fields.php`, configure `link_preview`:

| Key | Default | Meaning |
| --- | --- | --- |
| `cache_ttl_seconds` | `86400` | Metadata cache lifetime; clamped to `0`–`604800`. `0` disables persistence. Empty results cache for at most 30 seconds. |
| `rate_limit_per_minute` | `30` | Per authenticated user, separately for metadata and images; clamped to `1`–`600`. A value of `0` does not disable protection. |
| `timeout_seconds` | `8` | HTTP/DNS allowance tracked across redirects; clamped to `1`–`15` seconds. Native OS DNS can exceed it; isolated DNS is bounded separately. |
| `dns_strategy` | `auto` | `auto` uses isolated DNS on macOS PHP-FPM and native DNS elsewhere. `isolated` uses a fresh PHP CLI process with a two-second deadline; `native` uses the OS resolver directly. |
| `route_prefix` | `flex-fields` | Prefix of the metadata and image endpoints. Rebuild your Laravel route cache after changing it. |
| `middleware` | `['web', 'auth']` | Middleware for both endpoints. Use `auth:your-guard` for a custom guard. Authentication cannot be removed. The image route always also requires a relative URL signature. |

```php
'link_preview' => [
    'cache_ttl_seconds' => 43200,
    'rate_limit_per_minute' => 30,
    'timeout_seconds' => 8,
    'dns_strategy' => 'auto',
    'route_prefix' => 'flex-fields',
    'middleware' => ['web', 'auth:admin'],
],
```

Named routes: `filament-flex-fields.url-meta.scrape` and
`filament-flex-fields.url-meta.image`. Use route names rather than hardcoded paths.
The Laravel cache store must support atomic locks. Shared production cache also
shares rate limits and metadata across application instances.

## Loading and safety

- CSS and JS belong to each rendered field's asset boundary; closed tabs and modals wait for activation.
- Requests for the same URL and endpoint share in-flight work and a bounded browser cache.
- Changing the URL cancels the previous request; stale responses cannot replace the new preview.
- Removing the field releases its watchers, listeners, timers and pending image work.
- The server checks every redirect destination, rejects private/reserved addresses and pins DNS for the connection.
- Remote requests carry no application session cookies or authorization headers.
- Metadata transfers stop after the HTML head, or at the 256 KiB capture limit. Comments, script text and quoted attributes cannot masquerade as the head boundary. Redirect bodies are skipped. Image responses are limited to 2 MiB.
- Preview images use a short-lived signed local URL, stable within a five-minute interval so shared images can use the browser cache. The image endpoint revalidates redirects, accepts only supported raster images, and caps dimensions at 8 Mi pixels. SVG and HTML are rejected.

## Metadata selection

The parser reads a bounded HTML head without executing scripts or downloading
linked structured-data documents. Title selection uses Open Graph, Twitter,
HTML title and then JSON-LD. Description selection uses Open Graph, Twitter,
standard description/abstract tags and then JSON-LD.

Image selection preserves the first usable Open Graph image group, prefers its
secure alternate, then tries subsequent groups, Twitter, image metadata,
JSON-LD and supported link declarations. Empty, malformed and syntactically
private image candidates do not prevent trying a later candidate. Network
availability and actual raster safety are checked by the protected image endpoint.

JSON-LD supports WebPage, Article, NewsArticle, BlogPosting, Product and
VideoObject, including `@graph`, image objects and image lists. Up to 16 scripts
of 64 KiB each are examined with bounded graph traversal; malformed JSON is
ignored. Body-only metadata and client-rendered metadata are not evaluated.
Declared Windows-1252/ISO-8859-1 text is converted before extraction.

Open Graph image ordering follows the [Open Graph protocol](https://ogp.me/).

## Cache and concurrent requests

Server and browser metadata caches ignore URL fragments and preserve query
parameters. Cache hits avoid both DNS and remote HTTP. If another worker is
already fetching the same URL, the endpoint can return `503` with `Retry-After`;
the browser performs at most four bounded retries, cancelling them when the field
is removed or its value changes. The browser cache holds at most 64 entries for
five minutes and separates endpoints. Server cache TTL remains configurable.

Isolated DNS uses the matching PHP CLI executable and requires `proc_open`.
It does not use a third-party DNS-over-HTTPS service. Missing CLI/process support,
malformed DNS output, mixed private/public answers and failed lookups fail closed.
On other platforms, choose `isolated` when your deployment needs a hard DNS
process deadline; verify CLI availability as part of deployment.

The preview card announces updates politely; failures use an accessible alert.
Input styling supports light/dark themes and logical text alignment for RTL.

## Troubleshooting

<AccordionGroup>
  <Accordion title="The URL saves but there is no preview">
Check that the destination is public and serves HTML with title/Open Graph/Twitter metadata. Private addresses, authenticated pages and oversized responses cannot be previewed. A URL can remain valid for storage even when no preview is available.
  </Accordion>
  <Accordion title="Requests return 401 or 403">
Verify the configured authentication guard and logged-in session. Image URLs expire within five to ten minutes; requesting fresh metadata creates a new signature. Do not remove signing or authentication middleware.
  </Accordion>
  <Accordion title="Requests return 429">
The user's request limit was reached. Wait for the period indicated by the response's Retry-After header, or configure an appropriate bounded limit.
  </Accordion>
  <Accordion title="The endpoints return 404 after deployment">
Ensure the package provider is discovered and refresh the Laravel route cache after updating the package or endpoint configuration. Publish current assets so browser requests use the current route names.
  </Accordion>
</AccordionGroup>

See [custom component routes](/docs/extensions/routes) to declare protected endpoints
for your own extension, or try the Link Preview page in the live playground.
