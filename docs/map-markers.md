---
title: "MapMarkersField"
description: Add, move and cluster multiple locations, stored as a validated GeoJSON Point collection.
icon: "map-pin-plus"
"canonical": "https://flex-fields.bjanczak.com/docs/map-markers"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/map-markers.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "MapMarkersField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/map-markers-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/map-markers-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/map-markers.webp"
"twitter:image:alt": "MapMarkersField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/map-markers#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/map-markers",
      "name": "MapMarkersField",
      "description": "Add, move and cluster multiple locations, stored as a validated GeoJSON Point collection.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-markers#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/map-markers#article",
      "url": "https://flex-fields.bjanczak.com/docs/map-markers",
      "headline": "MapMarkersField",
      "description": "Add, move and cluster multiple locations, stored as a validated GeoJSON Point collection.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-markers#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/map-markers-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/map-markers-motion.mp4#video",
      "name": "MapMarkersField — FLEX Fields demonstration",
      "description": "A recorded demonstration of MapMarkersField in the FLEX Fields playground for Filament. Add, move and cluster multiple locations, stored as a validated GeoJSON Point collection.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/map-markers.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT10.017S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/map-markers-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-markers#article"
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
  src="/art/previews/map-markers-motion.mp4"
  poster="/art/previews/map-markers.webp"
  width="1920"
  height="1080"
  aria-label="MapMarkersField — FLEX Fields demonstration"
>
  <a href="/art/previews/map-markers-motion.mp4">Watch the MapMarkersField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`MapMarkersField` collects several locations in one field. Use it for delivery stops, branches, equipment positions or other sets of points. It enables marker collection mode automatically.

## Basic usage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapMarkersField;

MapMarkersField::make('stops')
    ->label('Delivery stops')
    ->mapProvider('maplibre')
    ->searchable(false)
    ->draggableMarkers()
    ->minFeatures(1)
    ->maxFeatures(50)
    ->clusterMarkers()
    ->fitFeatures()
    ->mapHeight(420)
    ->fullscreen()
    ->required();
```

This example adds points through the map without a geocoder. To enable address search, use `searchable()` and configure your [search provider and authenticated endpoints](/docs/location-v4#shared-address-search-endpoints). Selecting a search result adds a marker to the collection.

## State and database storage

Use a JSON column with an Eloquent `array` cast for `stops`. Each marker is a GeoJSON Point with a stable feature ID:

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "id": "stop-1",
      "geometry": {"type": "Point", "coordinates": [21.0122, 52.2297]},
      "properties": {"label": "Warehouse", "color": "#3b82f6"}
    }
  ]
}
```

Coordinates are `[longitude, latitude]`. Existing collections hydrate through the form; setting a camera center alone does not add a marker. Marker mode requires structured storage, not `storeFormat('string')`.

## Adding, moving and removing markers

Clicking the map adds a point when `selectOnMapClick()` is enabled. Select a marker to drag it. Use `manualCoordinates()` for coordinate entry and `geolocation()` for optional device location after a permission request initiated by the user.

`draggableMarkers(false)` disables marker dragging. `deletableFeatures(false)` prevents feature removal; `readOnly()` or a disabled field blocks edits. Undo and redo apply to committed edits; drag previews do not become separate history entries.

## Clustering and list layout

```php
MapMarkersField::make('stops')
    ->mapProvider('maplibre')
    ->searchable(false)
    ->clusterMarkers()
    ->clusterRadius(50)
    ->clusterMaxZoom(12)
    ->clusterMinimumPoints(3)
    ->maxFeatures(100)
    ->featureListMaxHeight(320);
```

Clusters show counts and expand on click. `clusterRadius()` accepts 20–100 pixels; the maximum cluster zoom must be below `maxZoom()`. Unselected points use GPU-rendered circles, with one selected DOM drag target per map instance.

Feature lists grow naturally unless `featureListMaxHeight()` bounds their scroll area. This setting accepts 128–4096 pixels; `null` restores natural height.

## Validation and custom actions

The server validates feature IDs, Point coordinates, allowed area policies and the configured feature-count limits. `maxFeatures()` can be configured up to the hard limit of 200 features. Labels and other admitted properties are validated independently of browser edits.

Use `featureSchema()` to edit custom marker data in a native Filament modal. `featureActions()` and `afterFeatureAdded()`, `afterFeatureUpdated()` and `afterFeatureDeleted()` connect accepted changes to your PHP code. See [feature forms, actions and events](/docs/map-feature-forms) for complete examples and persistence rules.

## Related components

- [MapPickerField](/docs/mappickerfield) — one address/location.
- [MapCoordinatesField](/docs/map-coordinates) — one coordinate pair without geocoding.
- [MapEditorField](/docs/map-editor) — points, lines and polygons in one collection.
- [Shared map configuration](/docs/location-v4) — providers, appearance and controls.
