---
title: "MapZonesField"
description: Draw and edit service polygons, rectangles and circles with server-side geometry validation.
icon: "scan"
"canonical": "https://flex-fields.bjanczak.com/docs/map-zones"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/map-zones-polygon.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "MapZonesField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/map-zones-polygon-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/map-zones-polygon-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/map-zones-polygon.webp"
"twitter:image:alt": "MapZonesField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/map-zones#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/map-zones",
      "name": "MapZonesField",
      "description": "Draw and edit service polygons, rectangles and circles with server-side geometry validation.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-zones#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/map-zones#article",
      "url": "https://flex-fields.bjanczak.com/docs/map-zones",
      "headline": "MapZonesField",
      "description": "Draw and edit service polygons, rectangles and circles with server-side geometry validation.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-zones#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/map-zones-polygon-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/map-zones-polygon-motion.mp4#video",
      "name": "MapZonesField — FLEX Fields demonstration",
      "description": "A recorded demonstration of MapZonesField in the FLEX Fields playground for Filament. Draw and edit service polygons, rectangles and circles with server-side geometry validation.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/map-zones-polygon.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT14.067S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/map-zones-polygon-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-zones#article"
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
  src="/art/previews/map-zones-polygon-motion.mp4"
  poster="/art/previews/map-zones-polygon.webp"
  width="1920"
  height="1080"
  aria-label="MapZonesField — FLEX Fields demonstration"
>
  <a href="/art/previews/map-zones-polygon-motion.mp4">Watch the MapZonesField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`MapZonesField` edits geographic service areas. It starts in zone collection mode with address search disabled. Use it for delivery areas, coverage regions and application-defined service boundaries.

## Basic usage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapZonesField;

MapZonesField::make('coverage')
    ->label('Delivery areas')
    ->mapProvider('maplibre')
    ->drawingTools(['polygon', 'rectangle', 'circle'])
    ->editableZones()
    ->deletableFeatures()
    ->snapDistance(12)
    ->drawingHistoryLimit(32)
    ->minFeatures(1)
    ->maxFeatures(20)
    ->maxZoneVertices(128)
    ->minZoneArea(100)
    ->maxZoneArea(50_000_000)
    ->requireZoneLabels()
    ->zoneColors(['#3b82f6', '#22c55e', '#f97316'])
    ->showLegend()
    ->fullscreen()
    ->required();
```

MapLibre's default map needs no Mapbox rendering token. Mapbox and custom styles are configured through the [shared map settings](/docs/location-v4).

## Drawing tools

| Tool | Interaction | Saved geometry |
| --- | --- | --- |
| Polygon | Place vertices, then close the shape or use Enter/Finish. | Polygon with one closed outer ring. |
| Rectangle | Select opposite corners. | Polygon. |
| Circle | Select a center and edge. | Geodesically sampled Polygon, not a proprietary radius geometry. |

Escape cancels a draft; Backspace removes its latest point. Drafts remain separate from committed form state. Circle sampling respects the configured vertex limit. Use `drawingTools([])` to prevent creation while retaining configured editing of existing zones.

## Editing and history

Select a zone to change its label/color, drag vertices, insert midpoint vertices, remove vertices, move the complete shape or rotate it in 15-degree steps. Arrow keys move selected vertices; Shift increases the step.

Each completed operation is validated atomically. An invalid operation leaves committed geometry unchanged. Undo/redo covers accepted geometry and metadata with a combined 2 MiB history/future budget; a new edit after undo clears the redo branch. Movement and rotation use the current map projection and are not surveying calculations.

`editableZones(false)` disables geometry/property editing; `deletableFeatures(false)` separately controls removal. Disabled/read-only fields block edits. `featureListMaxHeight(320)` bounds a long zone list instead of stretching the page.

## State and database storage

Use a JSON column with an Eloquent `array` cast for `coverage`. The field stores a GeoJSON FeatureCollection of Polygons. Coordinate pairs are `[longitude, latitude]`; the first and final pair close each ring.

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "id": "central-zone",
      "geometry": {
        "type": "Polygon",
        "coordinates": [[[21.00, 52.22], [21.02, 52.22], [21.02, 52.24], [21.00, 52.22]]]
      },
      "properties": {"label": "Central zone", "color": "#3b82f6"}
    }
  ]
}
```

Zones require structured storage. Changing an existing point/address column to a zone collection requires an application data migration; old address values are not automatically converted.

## Validation and overlap policies

The server independently validates coordinates, topology, feature counts, labels and configured area limits. Hard limits are 200 features and 10,000 total vertices. Each zone has one closed outer ring, 3–511 distinct vertices and nonzero area. Duplicate IDs, malformed numbers, self-crossings, touching nonadjacent edges and antimeridian-crossing edges are rejected.

Area limits use square meters and a spherical Earth model. They are not cadastral measurements. Allowed bounds/radius policies validate feature coordinates; they do not provide complete polygon containment analysis.

```php
MapZonesField::make('coverage')
    ->mapProvider('maplibre')
    ->preventZoneOverlap()
    ->allowZoneTouching()
    ->requireZoneLabels();
```

Use these policies when separate zones must not overlap but shared boundaries are acceptable. `requireZoneLabels()` is enforced on the server. Default feature text is plain text; custom metadata must be explicitly admitted.

## Custom data and supported scope

Use [feature forms, actions and events](/docs/map-feature-forms) for custom zone fields, native modal actions and PHP callbacks. Legends and map presentation use the [shared configuration](/docs/location-v4#feature-cards-and-coverage-legends).

The editor does not implement polygon holes, cutting/merging, boolean geometry operations, freehand tools or text annotations. Use [MapEditorField](/docs/map-editor) when the same field must also edit points and lines.
