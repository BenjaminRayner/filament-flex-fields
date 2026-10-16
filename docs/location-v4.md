---
title: "Shared map configuration"
description: Configure map providers, address-search endpoints, coordinate policies, controls and presentation shared by FLEX map fields.
icon: "settings-2"
---

Use this reference for settings shared by the geographic map components. Each component has its own setup, storage and interaction guide:

| Component | Use it for |
| --- | --- |
| [MapPickerField](/docs/mappickerfield) | One location with address search. |
| [MapCoordinatesField](/docs/map-coordinates) | One coordinate pair without address lookup. |
| [MapDisplayField](/docs/map-display) | Supplied geographic layers and legends. |
| [MapMarkersField](/docs/map-markers) | Several editable, optionally clustered points. |
| [MapZonesField](/docs/map-zones) | Polygons, rectangles and circles. |
| [MapEditorField](/docs/map-editor) | Mixed points, lines and polygons. |
| [CountryMapField](/docs/country-map-field) | Country selection on an SVG world map; no map SDK. |
| [AddressAutocompleteField](/docs/addressautocompletefield) | Address search without a rendered map. |

## Selection and storage modes

The specialized geographic components inherit the map renderer and shared controls from `MapPickerField`. Their defaults select the relevant mode:

| Mode | Component | Stored state |
| --- | --- | --- |
| Point | MapPickerField / MapCoordinatesField | Structured address/coordinate array, or configured address string. |
| Markers | MapMarkersField | GeoJSON FeatureCollection of Points. |
| Zones | MapZonesField | GeoJSON FeatureCollection of Polygons. |
| Features | MapEditorField | GeoJSON FeatureCollection of Point/LineString/Polygon. |

Geometry collections require structured storage and an Eloquent `array` cast for a JSON column. Changing a point/address field into a collection requires an application data migration. `mapFeatures()` overlays stay separate from submitted form state. MapDisplayField presents supplied layers; CountryMapField stores ISO country codes instead of GeoJSON.

### Rendering and search are independent

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;

MapPickerField::make('location')
    ->mapProvider('maplibre')
    ->fields(['lat', 'lng'])
    ->searchable(false)
    ->reverseGeocoding(false)
    ->manualCoordinates()
    ->geolocation()
    ->mapHeight(380)
    ->defaultCenter([52.2297, 21.0122]);
```

Mapbox remains the default renderer, preserving existing configurations and `mapboxToken()`. MapLibre uses OpenFreeMap styles by default and needs no Mapbox token **for rendering**. Address search still needs a configured geocoder. A custom server provider implements the existing `GeocodingProviderContract`; custom browser proxy endpoints must return the same feature/response contract used by the shared address adapter. Selecting MapLibre does not automatically provide an alternative search service.

Resource URLs are pinned: Mapbox GL JS 3.9.0 and MapLibre GL JS 6.11.2. `mapAssets()` supports self-hosted SDK/CSS; `mapWorkerUrl()` supports the chosen renderer's worker configuration. Developer-supplied SDKs must expose the compatible `Map`, `Marker`, `Popup`, `LngLatBounds` and `ScaleControl` API. Use one SDK version per provider per document. Tile/style licensing, tokens, attribution and CSP remain the host's provider configuration responsibilities. The component preserves renderer attribution.

### Shared address-search endpoints

With `mapbox.use_server_proxy` enabled (the default), Map Picker and Address
Autocomplete use the same two application endpoints:

| Purpose | Method and default path | Route name |
|---|---|---|
| Search addresses | `POST /flex-fields/geocode/search` | `filament-flex-fields.geocode.search` |
| Resolve coordinates to an address | `POST /flex-fields/geocode/reverse` | `filament-flex-fields.geocode.reverse` |

FLEX registers these endpoints automatically through one shared component
definition. Multiple maps or address fields do not register extra copies.
Address Autocomplete does not load a map SDK to share these endpoints.
Requests use your configured server geocoder, with input validation, caching and
rate limiting. The default `web` and `auth` middleware require an authenticated
session and protect POST requests with Laravel's CSRF middleware.

Configure the prefix or your application's authentication guard before routes
are cached:

```php config/filament-flex-fields.php
'mapbox' => [
    // Keep your token and other Mapbox settings here.
    'use_server_proxy' => true,
    'proxy_prefix' => 'flex-fields',
    'proxy_middleware' => ['web', 'auth:admin'],
],
```

Use `auth` for the default guard, or a guard configured in your application.
The proxy requires authentication; removing it from this middleware list is
rejected. Rebuild Laravel's route cache after changing the prefix or middleware.
Map tiles and renderer resources continue to use your chosen map provider;
these two endpoints serve address search and reverse geocoding.

### Map configuration reference

Most setters accept a Closure evaluated by Filament when the snapshot is built. `multipleMarkers()` and `drawZones()` accept booleans. Numeric and list bounds fail explicitly when the snapshot is resolved.

| Methods | Defaults / accepted values |
|---|---|
| `mapProvider()` | `mapbox` or `maplibre`; package default configured in `location.map_provider` |
| `mapStyle()`, `mapStyles()` | One style URL/v8 object, or light/dark URL pair; custom style overrides automatic style switching |
| `mapTheme()` | `auto`, `light`, `dark`; auto observes host dark class/data-theme |
| `mapAssets()` | `{script, stylesheet, module}`; URLs must be HTTPS or same-origin absolute paths |
| `mapWorkerUrl()` | Optional HTTPS or same-origin absolute worker path |
| `lazyMap()` | true; near-viewport SDK/map initialization |
| `mapHeight()` | 360 px; 240–1,200 px; reserved before SDK loading |
| `defaultCenter()`, `defaultZoom()` | Existing API: **[latitude, longitude]**, zoom 12 (legacy 1–22) |
| `minZoom()`, `maxZoom()` | 0/22; 0–22, min must not exceed max |
| `pitch()`, `bearing()` | 0; pitch 0–60°, bearing −360–360° |
| `mapBounds()` | Camera navigation bounds **[west,south,east,north]**; may wrap antimeridian |
| `allowedBounds()` | Selection-coordinate policy with same bounds format; independent of camera |
| `allowedRadius()` | `{center: [longitude,latitude], meters: number}`; 1–20,000,000 meters |
| `selectionMode()`, `multipleMarkers()`, `drawZones()` | `point`, `markers`, `zones`; explicit structured collection modes |
| `draggableMarkers()`, `selectOnMapClick()` | true; edits disabled by field disabled/readOnly state |
| `reverseGeocoding()` | true; applies to point selection; off gives coordinates without lookup latency |
| `geolocation()`, `geolocationHighAccuracy()` | false; GPS runs only after the user's location-button action |
| `geolocationTimeout()` | 10,000 ms; 1,000–60,000 ms |
| `manualCoordinates()`, `clearable()` | false/true; latitude/longitude inputs use shared FlexTextInput |
| `zoomControls()`, `compass()`, `fullscreen()`, `scale()` | true/true/false/false |
| `scaleUnit()` | metric, imperial, nautical |
| `scrollZoom()`, `cooperativeGestures()` | false/true |
| `fitFeatures()`, `animationDuration()` | false/450 ms; 0–3,000 ms; reduced-motion preference disables camera animation |
| `minFeatures()`, `maxFeatures()` | 0/100; 0–200 / 1–200, min ≤ max |
| `clusterMarkers()`, `clusterRadius()` | false/50 px; radius 20–100 px; marker mode |
| `markerColor()` | `#3b82f6`; #RRGGBB |
| `mapFeatures()`, `showLegend()` | Separate bounded overlay FeatureCollection / false |
| `drawingTools()` | polygon, rectangle, circle; subset (empty disables creating new shapes) |
| `editableZones()`, `deletableFeatures()` | true; separate modification/removal controls |
| `drawingHistoryLimit()` | 32; 1–100 entries plus combined 2 MiB byte budget |
| `snapDistance()` | 0 (off); 0–40 screen pixels to existing/draft vertices during drawing |
| `maxZoneVertices()` | 128; 3–511 excluding ring closure |
| `minZoneArea()`, `maxZoneArea()` | 0 / 5×10¹⁴ m²; ordered area policy |
| `zoneColors()` | Five default colors; 1–12 #RRGGBB entries |
| `requireZoneLabels()` | false; server enforces nonempty labels when enabled |
| `variant()` | primary, secondary, soft, flat; shared search-input appearance |

Coordinate order is deliberately explicit: the legacy `defaultCenter()` is latitude-first; GeoJSON, proximity and radius centers are longitude-first. GPS is optional, requires browser permission/secure context and is never requested during component initialization. A later map/search/manual selection or external state update invalidates an earlier pending GPS/reverse-geocoding intent.

### Shared search and editable addresses

Map Picker and Address Autocomplete share search scope, limits and optional editable address details. See [AddressAutocompleteField](/docs/addressautocompletefield#shared-search-and-editable-addresses) for the API and a complete example.

### Runtime ownership and assets

Map SDK objects, markers, observers, request leases and undo snapshots are kept outside Alpine's reactive graph under the stable component root. Destroy disconnects observers, disposes requests, removes map/marker/popup objects and cancels scheduled work. Resize uses one coalesced frame and ignores unchanged/zero dimensions. SDK/CSS loading is single-flight per provider/resource identity; failed owned nodes are cleaned and loading can be retried. Only near-viewport map instances initialize. The plain CountryMap SVG view needs neither SDK nor geocoding.

`map-picker`, `address-autocomplete`, `country-map-field` request their own assets/dependencies through the existing asset manager. FlexTextInput, Select, teleported menu and the shared checkbox indicator are dependencies deduplicated across consumers. No page-wide registration of every package component is introduced.

## Appearance

`MapPickerField::make('location')->rounding('xl')` uses the same `md`, `lg`, `xl`, `native` or `full` radius configuration/default as the other package fields. Map surfaces and zone cards inherit it; floating toolbars always use fully rounded capsules. Map search uses the shared FlexTextInput styling through `size()` and `variant()`.

## Feature cards and coverage legends

`featurePopups(false)` disables cards. They are enabled by default for marker/overlay selection. `showLegend()->legendTitle('Coverage zones')->legendDescription('Delivery capacity')` displays a card on the map using state features in collection modes or configured overlays otherwise. Point/zone selection from the card uses the same selection controller as the map.

Feature `properties` admit bounded plain text: `label`, `description`, `detail`, `value`, `metric`, `tooltip`, plus hexadecimal `color` and boolean `clickable`. Cards display title, description, detail and emphasized value; legend rows display label and optional metric. These fields never execute HTML. Unrecognized properties are discarded. Map controls use only the supplied artwork in `resources/svg/maps`, rendered through the bounded MapIcon catalog. Point markers are plain circles, with no pin SVG. One card, one hover tooltip and at most one selected DOM marker belong to each map instance. The GPU point filter hides that selected marker without removing it from cluster data.

Floating controls for geographic and country maps share the lazy `location-controls` CSS dependency. Their button/icon dimensions and capsule surface are identical, with blur and inset edge highlights. No SDK is loaded for the SVG country map.

## Feature lists, forms and events

Marker and zone lists grow naturally. Use `featureListMaxHeight(320)` to bound a long list; the accepted range is 128–4096 pixels, and `null` restores natural height.

Collection components also support [custom feature forms, native actions and PHP events](/docs/map-feature-forms). Those settings do not create separate database records automatically.
