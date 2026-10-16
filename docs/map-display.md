---
title: "MapDisplayField"
description: Present supplied GeoJSON points, lines and coverage areas with configurable cards and legends.
icon: "map"
"canonical": "https://flex-fields.bjanczak.com/docs/map-display"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/map-display.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "MapDisplayField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/map-display-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/map-display-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/map-display.webp"
"twitter:image:alt": "MapDisplayField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/map-display#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/map-display",
      "name": "MapDisplayField",
      "description": "Present supplied GeoJSON points, lines and coverage areas with configurable cards and legends.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-display#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/map-display#article",
      "url": "https://flex-fields.bjanczak.com/docs/map-display",
      "headline": "MapDisplayField",
      "description": "Present supplied GeoJSON points, lines and coverage areas with configurable cards and legends.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-display#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/map-display-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/map-display-motion.mp4#video",
      "name": "MapDisplayField — FLEX Fields demonstration",
      "description": "A recorded demonstration of MapDisplayField in the FLEX Fields playground for Filament. Present supplied GeoJSON points, lines and coverage areas with configurable cards and legends.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/map-display.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT9.233S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/map-display-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-display#article"
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
  src="/art/previews/map-display-motion.mp4"
  poster="/art/previews/map-display.webp"
  width="1920"
  height="1080"
  aria-label="MapDisplayField — FLEX Fields demonstration"
>
  <a href="/art/previews/map-display-motion.mp4">Watch the MapDisplayField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`MapDisplayField` presents supplied geographic data. It starts with `readOnly()` and address search disabled, so users can inspect a map without editing its geometry.

## Basic usage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapDisplayField;

MapDisplayField::make('coverage_overview')
    ->label('Service coverage')
    ->mapProvider('maplibre')
    ->mapFeatures([
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'id' => 'office',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [21.0122, 52.2297],
                ],
                'properties' => [
                    'label' => 'Main office',
                    'description' => 'Customer collection point',
                    'color' => '#3b82f6',
                ],
            ],
        ],
    ])
    ->showLegend()
    ->legendTitle('Service locations')
    ->legendDescription('Select a location to inspect it')
    ->fitFeatures()
    ->fullscreen()
    ->mapHeight(440)
    ->dehydrated(false);
```

`dehydrated(false)` excludes this presentation-only field from the form's saved data. Choose it explicitly when the field has no corresponding model attribute.

## Supplied features and form state

`mapFeatures()` accepts a bounded GeoJSON FeatureCollection containing Points, LineStrings and Polygons. GeoJSON coordinates use `[longitude, latitude]`. For example, provide a collection from an application service with `mapFeatures(fn () => $coverageService->features())`.

Overlay features remain separate from the field's submitted state. Displaying a route or coverage polygon does not create or update database records. Load saved geometry from your model or service and pass it to `mapFeatures()`; use an editable component when the form must modify that geometry.

## Cards, tooltips and legends

| Configuration | Result |
| --- | --- |
| `showLegend()` | Display a selectable feature legend. |
| `legendTitle()`, `legendDescription()` | Set your own legend text. |
| `featurePopups(false)` | Hide click cards while retaining the legend. |
| `featureTooltips(false)` | Hide hover/focus labels. |
| `fitFeatures()` | Fit the initial camera to supplied features. |

Feature properties can include `label`, `description`, `detail`, `value`, `metric`, `tooltip`, `color` and `clickable`. Text is bounded and rendered as plain text. `clickable: false` suppresses an individual feature's card. Unknown properties are discarded unless admitted through the applicable feature-property configuration.

You can supply existing route LineStrings, but the component does not calculate directions or optimize routes. Use a routing service in your application to produce them.

## Map navigation

`fullscreen()`, `scale()`, `mapBounds()`, `defaultCenter()`, `defaultZoom()` and theme/style options use the [shared map configuration](/docs/location-v4). Read-only geometry does not prevent users from inspecting the map with its viewing controls.

## Related components

- [MapMarkersField](/docs/map-markers) — edit a collection of locations.
- [MapZonesField](/docs/map-zones) — draw and edit service areas.
- [MapEditorField](/docs/map-editor) — edit mixed points, lines and polygons.
