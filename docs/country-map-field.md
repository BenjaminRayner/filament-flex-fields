---
title: "CountryMapField"
description: Select ISO countries on an SVG world map synchronized with a searchable country dropdown.
icon: "globe"
"canonical": "https://flex-fields.bjanczak.com/docs/country-map-field"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/country-map-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "CountryMapField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/country-map-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/country-map-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/country-map-field.webp"
"twitter:image:alt": "CountryMapField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/country-map-field#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/country-map-field",
      "name": "CountryMapField",
      "description": "Select ISO countries on an SVG world map synchronized with a searchable country dropdown.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/country-map-field#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/country-map-field#article",
      "url": "https://flex-fields.bjanczak.com/docs/country-map-field",
      "headline": "CountryMapField",
      "description": "Select ISO countries on an SVG world map synchronized with a searchable country dropdown.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/country-map-field#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/country-map-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/country-map-field-motion.mp4#video",
      "name": "CountryMapField — FLEX Fields demonstration",
      "description": "A recorded demonstration of CountryMapField in the FLEX Fields playground for Filament. Select ISO countries on an SVG world map synchronized with a searchable country dropdown.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/country-map-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT9S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/country-map-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/country-map-field#article"
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
  src="/art/previews/country-map-field-motion.mp4"
  poster="/art/previews/country-map-field.webp"
  width="1920"
  height="1080"
  aria-label="CountryMapField — FLEX Fields demonstration"
>
  <a href="/art/previews/country-map-field-motion.mp4">Watch the CountryMapField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`CountryMapField` combines a world map and country selector. It needs no geographic map SDK or map-provider token.

## Multiple countries

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryMapField;

CountryMapField::make('markets')
    ->countries(['BR', 'MX', 'AR', 'CL', 'PL', 'DE'])
    ->countryGroups([
        'Latin America' => ['BR', 'MX', 'AR', 'CL'],
        'Europe' => ['PL', 'DE'],
    ])
    ->mapHeight(320)
    ->mapColor('#22c55e')
    ->default(['BR', 'MX']);
```

## State and validation

Default state is an array of uppercase ISO alpha-2 codes. `multiple(false)` changes it to a code or null and hides bulk groups. `countries(null)` admits the full country catalog; an explicit list constrains map and dropdown. Named group countries must belong to the allowed set. There may be at most 32 named groups. Submitted selection size is bounded by the unique allowed country catalog; duplicates and unsupported codes are rejected. Field disabled/readOnly policies block both SVG and list edits.

## Map and list interactions

CountryMap uses the shared SelectField engine: rich flags/names, chips, searching, virtualization/menu lifecycle, desktop dropdown and mobile sheet. Names sort with the current locale's ICU collator when available. Country group checkboxes show none/partial/all selected states and counts; their design/animation uses the same indicator asset as ChoiceCheckboxCards. Group membership and individual choices update the same entangled ISO state. Map regions support Enter/Space activation; list selection provides a keyboard alternative. Zoom/pan never changes selected values.

`mapHeight()` accepts 200–800 px; `mapColor()` accepts #RRGGBB. The embedded simplified dataset has 173 outlines. Countries/territories without an outline remain selectable in the full list. This map is a selection aid, not an authoritative border/geopolitical dataset. Outlines come from the MIT jsvectormap 1.7.0 world dataset (verified package integrity), with attribution/license in `resources/licenses/jsvectormap-world-LICENSE.txt`. No jsvectormap rendering library code or third-party editor code is used.

The world surface itself needs no map API, tile requests, geolocation or WebGL. Country flags use the shared local flag assets also used by [CountryField](/docs/countryfield) and [PhoneField](/docs/phonefield). Geographic provider options in the [shared map reference](/docs/location-v4) apply to the separate map components, not this SVG country map.

The playground configures three explicit bulk groups (Latin America, Sub-Saharan Africa, Central Asia). Applications define their own `countryGroups()` policies; region membership is not assumed by the field. Group rows and country rows use the same checkbox column and selection indicator; counts show selected/total. A Countries heading reuses the shared Select group heading style. Bulk toggles remain keyboard accessible.

## Single country

```php
CountryMapField::make('market')
    ->multiple(false)
    ->countries(['PL', 'DE', 'FR'])
    ->default('PL');
```

Single selection stores an uppercase ISO alpha-2 string such as `PL`, or `null`. Use a scalar model attribute for this mode. Multiple selection stores an array of ISO codes; use a JSON column with an Eloquent `array` cast. Labels, translated names and SVG paths are not the saved value.

## Appearance and camera

| Method | Purpose |
| --- | --- |
| `mapHeight(320)` | Set the SVG viewport height, 200–800 pixels. |
| `mapColor('#22c55e')` | Set the selection color using #RRGGBB. |
| `mapZoomRange(1, 6)` | Configure the country map's zoom range. |
| `mapAnimationDuration(180)` | Configure camera transition duration. |
| `mapScrollZoom()` | Enable wheel zoom, disabled by default. |
| `mapTooltips(false)` | Hide map region tooltips. |

## Related components

- [CountryField](/docs/countryfield) — the country list without an SVG map.
- [MapPickerField](/docs/mappickerfield) — a geographic address/location picker.
- [MapZonesField](/docs/map-zones) — application-defined geographic areas.
