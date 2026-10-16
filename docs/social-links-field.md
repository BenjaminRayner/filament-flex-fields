---
title: "Social links"
description: "Build a social profile editor for Filament with FLEX Select, shared text inputs, Hugeicons, platform-specific URL validation and custom platforms."
icon: "share-2"
"canonical": "https://flex-fields.bjanczak.com/docs/social-links-field"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/social-links-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Social links in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/social-links-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/social-links-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/social-links-field.webp"
"twitter:image:alt": "Social links in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/social-links-field#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/social-links-field",
      "name": "Social links",
      "description": "Build a social profile editor for Filament with FLEX Select, shared text inputs, Hugeicons, platform-specific URL validation and custom platforms.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/social-links-field#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/social-links-field#article",
      "url": "https://flex-fields.bjanczak.com/docs/social-links-field",
      "headline": "Social links",
      "description": "Build a social profile editor for Filament with FLEX Select, shared text inputs, Hugeicons, platform-specific URL validation and custom platforms.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/social-links-field#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/social-links-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/social-links-field-motion.mp4#video",
      "name": "Social links — FLEX Fields demonstration",
      "description": "A recorded demonstration of Social links in the FLEX Fields playground for Filament. Build a social profile editor for Filament with FLEX Select, shared text inputs, Hugeicons, platform-specific URL validation and custom platforms.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/social-links-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT11S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/social-links-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/social-links-field#article"
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
  src="/art/previews/social-links-field-motion.mp4"
  poster="/art/previews/social-links-field.webp"
  width="1920"
  height="1080"
  aria-label="Social links — FLEX Fields demonstration"
>
  <a href="/art/previews/social-links-field-motion.mp4">Watch the Social links demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Choose a platform with FLEX Select, add it and paste its profile link. Each platform has one row, a Hugeicons brand icon and its own URL placeholder. URL controls reuse FLEX Text Input's sizes, surfaces, rounding and focus outline.

<CardGroup cols={2}>
  <Card title="Profiles that match the platform" icon="shield-check">Server validation checks the URL's scheme and hostname, rejects duplicate platforms and enforces your link limit.</Card>
  <Card title="Your own platforms" icon="globe">Add a community, customer portal or company website with its own label, icon and allowed domains.</Card>
</CardGroup>

## Start with one field

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SocialLinksField;

SocialLinksField::make('social_links')
    ->label('Social profiles')
    ->helperText('Add each platform once, then paste its profile URL.');
```

Store the field in a JSON column with an Eloquent `array` cast:

```php
protected function casts(): array
{
    return ['social_links' => 'array'];
}
```

The saved value preserves row order:

```json
[
  {"platform": "instagram", "url": "https://instagram.com/your_company"},
  {"platform": "website", "url": "https://example.com"}
]
```

An empty optional field saves as `null`. `required()` requires at least one completed link. Adding a row with an empty URL makes that row incomplete: enter its URL or remove it before saving.

## Choose the available platforms

```php
use Bjanczak\FilamentFlexFields\Data\SocialPlatform;

SocialLinksField::make('footer_links')
    ->platforms([
        SocialPlatform::Instagram,
        SocialPlatform::LinkedIn,
        SocialPlatform::YouTube,
        SocialPlatform::Website,
    ])
    ->maxLinks(3);
```

`platforms()` accepts enum cases, slugs or a Closure. Its order determines the choice order. `null` uses the default set; an explicit empty list offers no choices. Unknown platform names are configuration errors. Platforms already added to the list are removed from the choices and return when their rows are deleted.

| Enum case | Slug | Allowed domains |
| --- | --- | --- |
| `Instagram` | `instagram` | instagram.com, instagr.am |
| `X` | `x` | x.com, twitter.com |
| `LinkedIn` | `linkedin` | linkedin.com |
| `YouTube` | `youtube` | youtube.com, youtu.be, youtube-nocookie.com |
| `Facebook` | `facebook` | facebook.com, fb.com, fb.me |
| `TikTok` | `tiktok` | tiktok.com |
| `GitHub` | `github` | github.com |
| `Telegram` | `telegram` | t.me, telegram.me, telegram.org |
| `WhatsApp` | `whatsapp` | wa.me, api.whatsapp.com, whatsapp.com |
| `Pinterest` | `pinterest` | pinterest.com, pin.it |
| `Threads` | `threads` | threads.com, threads.net |
| `Discord` | `discord` | discord.com, discord.gg |
| `Messenger` | `messenger` | m.me, messenger.com, m.facebook.com |
| `Reddit` | `reddit` | reddit.com |
| `Twitch` | `twitch` | twitch.tv |
| `Vimeo` | `vimeo` | vimeo.com |
| `Vk` | `vk` | vk.com, vk.ru |
| `Website` | `website` | Any valid HTTP or HTTPS URL |

Both current Threads.com URLs and older Threads.net URLs are accepted, following [Meta’s domain migration](https://about.fb.com/news/2025/04/new-features-threads-web-experience/).

Subdomains of an allowed domain are accepted. A lookalike such as `instagram.com.example.org` is rejected for Instagram.

### Exclude platforms

```php
SocialLinksField::make('social_links')
    ->excludePlatforms([
        SocialPlatform::Vk,
        SocialPlatform::Reddit,
    ]);
```

Exclusions apply to the default set, explicit selections and custom platforms.

### Dynamic platform selection

```php
use Filament\Schemas\Components\Utilities\Get;

SocialLinksField::make('links')
    ->platforms(fn (Get $get): array => $get('is_public')
        ? [SocialPlatform::Instagram, SocialPlatform::Website]
        : [SocialPlatform::Website]);
```

Make the controlling field `live()` so Filament refreshes the configuration. Existing values are not silently deleted when a platform becomes unavailable; remove those rows before saving. The server validates against the current configuration.

## Add your own platform

```php
SocialLinksField::make('community_links')
    ->customPlatforms([
        [
            'value' => 'community',
            'label' => 'Customer community',
            'placeholder' => 'https://community.example.com/profile/name',
            'hosts' => ['community.example.com'],
            'icon' => 'hugeicons-user-group',
        ],
    ])
    ->platforms(['community', SocialPlatform::Website]);
```

| Custom platform key | Required? | Meaning |
| --- | --- | --- |
| `value` | Yes | Unique lowercase slug, starting with a letter; letters, digits, `_` and `-`, up to 128 characters |
| `label` | No | Visible name; defaults to the slug |
| `placeholder` | No | URL hint; defaults to `https://` |
| `hosts` | No | Domain names without schemes or paths; an empty list permits any valid HTTP/HTTPS URL |
| `icon` | No | Registered Blade icon name; defaults to `hugeicons-globe` |
| `iconSvg` | No | Compatibility option for SVG markup supplied by trusted PHP configuration |

Use `icon` for Hugeicons. A custom `mastodon` slug also resolves to `hugeicons-mastodon` when no icon is specified. Never put user-submitted HTML into `iconSvg`. Custom definitions are appended to the default set unless you provide `platforms()`. At most 128 platforms can be configured.

### Offer only your own platforms

Provide both the definitions and an explicit choice list. Built-in platforms will not appear:

```php
SocialLinksField::make('company_links')
    ->customPlatforms([
        [
            'value' => 'mastodon',
            'label' => 'Mastodon',
            'hosts' => ['mastodon.social'],
            'icon' => 'hugeicons-mastodon',
        ],
        [
            'value' => 'portal',
            'label' => 'Customer portal',
            'hosts' => ['portal.example.com'],
            'icon' => 'hugeicons-user-account',
        ],
    ])
    ->platforms(['mastodon', 'portal']);
```

For federated services, list the instances you allow in `hosts`. An empty host list intentionally permits any valid HTTP/HTTPS domain; provide domains when links must be restricted.

## Order and URL formatting

```php
SocialLinksField::make('links')
    ->reorderable()
    ->autoFormatUrls();
```

Reordering adds **Move up** and **Move down** buttons. Their order is stored in the JSON value. The boundary button is disabled at the first or last row; all actions support keyboard activation.

By default, entering `example.com/profile` becomes `https://example.com/profile` when the input loses focus. Existing schemes are preserved and still validated. `autoFormatUrls(false)` requires users to supply a complete HTTP or HTTPS URL themselves. Formatting does not bypass domain validation.

## Validation and safe storage

The server validates before normal Filament form state is saved. It rejects:

- Unknown or excluded platforms and duplicate platform rows.
- Incomplete rows, non-HTTP schemes, embedded URL credentials and invalid hostnames.
- More rows than `maxLinks()` permits.
- Malformed payloads, nested URL values, unexpected row keys, control characters and invalid UTF-8.
- URLs over 4,096 bytes, platform values over 128 bytes and more than 128 rows.

```php
SocialLinksField::make('required_profiles')
    ->platforms([SocialPlatform::Instagram, SocialPlatform::Website])
    ->maxLinks(2)
    ->required();
```

Inline feedback appears after leaving a URL control or attempting submission. Server validation remains authoritative for modal actions and requests that bypass the browser. A valid domain does not prove that a profile exists or belongs to the user: add application verification when ownership matters. The field does not fetch these URLs.

Legacy associative values such as `['website' => 'https://example.com']` are accepted and saved in the list format. Malformed values are preserved for rejection rather than converted into an empty valid field.

## Appearance and access

```php
SocialLinksField::make('compact_links')
    ->size('sm')
    ->variant('secondary')
    ->rounding('full')
    ->focusOutline();
```

Sizes are `sm`, `md`, `lg`. Variants are `primary`, `secondary`, `soft`, `flat`; `ghost` uses the flat input surface. URL inputs and the platform selector reuse the shared controls. Brand icons use Hugeicons with stroke width 2. The layout supports light/dark themes, narrow screens and RTL without a separate mobile input implementation.

```php
SocialLinksField::make('links')->readOnly();
SocialLinksField::make('links')->disabled();
```

Read-only fields retain readable values and block editing, adding, removing and reordering. Disabled state also follows Filament's normal dehydration rules. Neither setting replaces server authorization.

## Configuration reference

| Method | Default | Purpose |
| --- | --- | --- |
| `platforms(array\|Closure\|null)` | All 18 built-in platforms | Explicit choice list; `null` restores defaults |
| `excludePlatforms(array\|Closure)` | `[]` | Remove platforms from the configured choice set |
| `customPlatforms(array\|Closure)` | `[]` | Add your platform definitions |
| `maxLinks(int\|Closure\|null)` | `null` | Business limit of 1–128; `null` leaves the configured choice count as the effective limit |
| `reorderable(bool\|Closure)` | `false` | Enable row ordering controls |
| `autoFormatUrls(bool\|Closure)` | `true` | Add HTTPS to scheme-less URLs on blur |
| `variant(string\|Closure)` | `primary` | Shared control surface |
| `size(string\|ControlSize\|Closure)` | `md` | Shared control size |
| `rounding(...)` | Shared default | Shared control radius |
| `focusOutline(bool\|Closure)` | `true` | Whole-control focus outline |

Normal field methods such as `label()`, `helperText()`, `required()`, `readOnly()`, `disabled()` and state binding modifiers remain available. Configured values may be evaluated through Filament Closure injection.

## JSON custom-field schemas

```php
[
    'type' => 'social_links',
    'slug' => 'company_profiles',
    'label' => 'Company profiles',
    'config' => [
        'platforms' => ['instagram', 'linkedin', 'website'],
        'max_links' => 3,
        'reorderable' => true,
        'auto_format_urls' => true,
        'variant' => 'secondary',
        'size' => 'md',
    ],
]
```

Social configuration keys are `platforms`, `exclude_platforms`, `custom_platforms`, `max_links`, `reorderable` and `auto_format_urls`. Common appearance and field settings use the normal schema configuration.

## Assets and lifecycle

The rendered field declares its own asset boundary and uses the existing FLEX Select and text-input presentation dependencies. Shared CSS and JavaScript are deduplicated; URL rows do not start standalone text-input factories or copy the select engine. Inactive tabs and closed modals activate these dependencies only when opened. Rendered rows are reused during initialization, and changes reconcile by platform rather than replacing the entire list.

Follow the standard [installation and asset publication](/docs/installation) steps. No additional dependency or social-icon publishing command is needed.
