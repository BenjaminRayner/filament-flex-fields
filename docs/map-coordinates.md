---
title: "MapCoordinatesField"
description: Choose latitude and longitude on a map, through coordinate inputs or with optional browser geolocation.
icon: "locate-fixed"
"canonical": "https://flex-fields.bjanczak.com/docs/map-coordinates"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/map-coordinates.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "MapCoordinatesField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/map-coordinates-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/map-coordinates-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/map-coordinates.webp"
"twitter:image:alt": "MapCoordinatesField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/map-coordinates#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/map-coordinates",
      "name": "MapCoordinatesField",
      "description": "Choose latitude and longitude on a map, through coordinate inputs or with optional browser geolocation.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-coordinates#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/map-coordinates#article",
      "url": "https://flex-fields.bjanczak.com/docs/map-coordinates",
      "headline": "MapCoordinatesField",
      "description": "Choose latitude and longitude on a map, through coordinate inputs or with optional browser geolocation.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-coordinates#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/map-coordinates-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/map-coordinates-motion.mp4#video",
      "name": "MapCoordinatesField — FLEX Fields demonstration",
      "description": "A recorded demonstration of MapCoordinatesField in the FLEX Fields playground for Filament. Choose latitude and longitude on a map, through coordinate inputs or with optional browser geolocation.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/map-coordinates.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT11.55S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/map-coordinates-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-coordinates#article"
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
  src="/art/previews/map-coordinates-motion.mp4"
  poster="/art/previews/map-coordinates.webp"
  width="1920"
  height="1080"
  aria-label="MapCoordinatesField — FLEX Fields demonstration"
>
  <a href="/art/previews/map-coordinates-motion.mp4">Watch the MapCoordinatesField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`MapCoordinatesField` selects a single geographic point without address search or reverse geocoding. Use it for equipment positions, meeting points and coordinates that do not need a street address.

## Basic usage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapCoordinatesField;

MapCoordinatesField::make('coordinates')
    ->label('Meeting point')
    ->mapProvider('maplibre')
    ->defaultCenter([52.2297, 21.0122])
    ->defaultZoom(12)
    ->mapHeight(420)
    ->geolocation()
    ->fullscreen()
    ->required();
```

The component enables manual coordinate inputs and stores `lat` and `lng`. Address search and reverse geocoding are disabled by default. MapLibre's default rendering setup needs no Mapbox token; a custom tile/style provider may have its own requirements.

## State and database storage

Use a JSON column with an Eloquent `array` cast:

```php
protected function casts(): array
{
    return ['coordinates' => 'array'];
}
```

```json
{"lat": 52.2297, "lng": 21.0122}
```

`defaultCenter()` sets the initial camera; it does not select or save a point. Use `default(['lat' => 52.2297, 'lng' => 21.0122])` for an initial field value. Existing model values hydrate through the normal Filament form flow.

## Input and selection policies

```php
MapCoordinatesField::make('coordinates')
    ->mapProvider('maplibre')
    ->manualCoordinates()
    ->selectOnMapClick()
    ->draggableMarkers()
    ->allowedBounds([14, 49, 24.2, 55])
    ->clearable()
    ->scale()
    ->scaleUnit('nautical');
```

Use `manualCoordinates(false)` to hide the numeric inputs, `selectOnMapClick(false)` to disable point placement by clicking, and `draggableMarkers(false)` to disable dragging. `readOnly()` blocks value changes while retaining map viewing controls.

Latitude and longitude are validated on the server, together with configured bounds/radius policies. The coordinate inputs are not the validation boundary. `requiredFields(['lat', 'lng'])` is configured by the component; `required()` additionally makes an empty selection invalid.

## Optional device location

`geolocation()` adds a location action. It requests browser permission only after the user activates it, requires a secure browser context and can fail if permission or a device location is unavailable. It does not continuously track the device.

See [shared map configuration](/docs/location-v4) for providers, GPS timeouts, camera controls, appearance and coordinate order. `defaultCenter()` takes `[latitude, longitude]`; GeoJSON and radius centers take `[longitude, latitude]`.

## Related components

- [MapPickerField](/docs/mappickerfield) — select a point with a structured address.
- [MapMarkersField](/docs/map-markers) — collect several points.
- [MapDisplayField](/docs/map-display) — present supplied map layers.
