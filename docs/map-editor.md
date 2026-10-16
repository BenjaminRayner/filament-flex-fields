---
title: "MapEditorField"
description: Edit points, polylines, polygons, rectangles and circles in one validated GeoJSON collection.
icon: "pencil-ruler"
"canonical": "https://flex-fields.bjanczak.com/docs/map-editor"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/map-editor.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "MapEditorField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/map-editor-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/map-editor-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/map-editor.webp"
"twitter:image:alt": "MapEditorField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/map-editor#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/map-editor",
      "name": "MapEditorField",
      "description": "Edit points, polylines, polygons, rectangles and circles in one validated GeoJSON collection.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-editor#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/map-editor#article",
      "url": "https://flex-fields.bjanczak.com/docs/map-editor",
      "headline": "MapEditorField",
      "description": "Edit points, polylines, polygons, rectangles and circles in one validated GeoJSON collection.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-editor#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/map-editor-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/map-editor-motion.mp4#video",
      "name": "MapEditorField — FLEX Fields demonstration",
      "description": "A recorded demonstration of MapEditorField in the FLEX Fields playground for Filament. Edit points, polylines, polygons, rectangles and circles in one validated GeoJSON collection.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/map-editor.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT19.7S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/map-editor-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/map-editor#article"
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
  src="/art/previews/map-editor-motion.mp4"
  poster="/art/previews/map-editor.webp"
  width="1920"
  height="1080"
  aria-label="MapEditorField — FLEX Fields demonstration"
>
  <a href="/art/previews/map-editor-motion.mp4">Watch the MapEditorField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`MapEditorField` combines points, lines and zones in one field. It enables mixed-feature mode automatically and starts with address search disabled. Use it for service plans, venue layouts or collections that need more than one geometry type.

## Basic usage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapEditorField;

MapEditorField::make('service_map')
    ->mapProvider('maplibre')
    ->allowedGeometryTypes(['Point', 'LineString', 'Polygon'])
    ->drawingTools(['point', 'polyline', 'polygon', 'rectangle', 'circle'])
    ->maxFeatures(40)
    ->maxLineVertices(64)
    ->maxZoneVertices(128)
    ->snapDistance(12)
    ->geometryStrokeWidth(2)
    ->zoneFillOpacity(.18)
    ->pointSize(18)
    ->featureTooltips()
    ->showZoneMetrics()
    ->drawingHistoryLimit(32)
    ->fullscreen();
```

Rendering, styles, camera limits and controls use the [shared map configuration](/docs/location-v4). Add `searchable()` only when you also configure an address-search provider; search adds points rather than generating lines or zones.

## Tools and geometry restrictions

| Drawing tool | GeoJSON type |
| --- | --- |
| `point` | Point |
| `polyline` | LineString |
| `polygon`, `rectangle`, `circle` | Polygon |

`allowedGeometryTypes()` is enforced on the server and must agree with `drawingTools()`. For a point-and-line editor, configure both lists:

```php
MapEditorField::make('inspection_path')
    ->mapProvider('maplibre')
    ->allowedGeometryTypes(['Point', 'LineString'])
    ->drawingTools(['point', 'polyline'])
    ->maxLineVertices(64);
```

Enter/Finish commits a polyline with at least two distinct adjacent vertices. Escape cancels a draft and Backspace removes its latest point. Polygon, rectangle and circle interactions follow the [zone editor](/docs/map-zones#drawing-tools).

## Editing and history

Selected lines support dragging or keyboard movement of vertices, inserting segment midpoints, removing vertices down to two, whole-line movement and rotation. Polygon editing follows the same committed-operation history. Invalid completed geometry does not replace accepted form state.

`editableZones(false)` disables line/polygon vertex and property editing. Point dragging has its own `draggableMarkers()` policy. `deletableFeatures(false)` controls removal separately; disabled/read-only fields block edits.

Undo/redo spans geometry types and metadata with a bounded history budget. New edits after undo discard the redo branch. Use [feature forms and actions](/docs/map-feature-forms) for custom geometry-specific modal forms and PHP callbacks.

## State and database storage

Use a JSON column with an Eloquent `array` cast. The value is a GeoJSON FeatureCollection containing the admitted Point, LineString and Polygon features. Each feature needs a unique stable ID. GeoJSON coordinate pairs use `[longitude, latitude]`.

Collection modes require structured storage. Supplied `mapFeatures()` overlays are separate from this editable state and are not automatically persisted with it.

## Limits and appearance

| Method | Accepted values |
| --- | --- |
| `maxFeatures()` | Up to 200 features; configure a smaller application limit. |
| `maxLineVertices()` | 2–512 vertices per line. |
| `maxZoneVertices()` | 3–511 distinct outer-ring vertices. |
| `geometryStrokeWidth()` | 0.5–12 pixels. |
| `zoneFillOpacity()` | 0–1. |
| `pointSize()` | 12–48 pixels for GPU points and the selected DOM circle. |
| `snapDistance()` | 0–40 screen pixels; zero disables snapping. |
| `featureTooltips(false)` | Hide hover/focus labels. |
| `showZoneMetrics(false)` | Hide zone metric labels. |

The geometry boundary also enforces a 10,000-total-vertex limit and validates submitted topology independently of the browser. Zone-specific area and overlap policies are described on [MapZonesField](/docs/map-zones#validation-and-overlap-policies).

## Supported scope

Lines represent geometry supplied or drawn by your application; the field does not calculate routes. Mixed-feature mode is unclustered. Use [MapMarkersField](/docs/map-markers) for clustered Point collections.

Polygon holes, polygon cutting/merging, freehand drawing, text annotations and continuous device GPS tracking are not implemented.
