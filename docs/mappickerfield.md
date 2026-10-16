---
title: "MapPickerField"
description: Select one location with address search, a draggable point and configurable coordinate/address storage.
icon: "map-pinned"
"canonical": "https://flex-fields.bjanczak.com/docs/mappickerfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/map-picker-structured.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "MapPickerField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/map-picker-structured-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/map-picker-structured-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/map-picker-structured.webp"
"twitter:image:alt": "MapPickerField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/mappickerfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/mappickerfield",
      "name": "MapPickerField",
      "description": "Select one location with address search, a draggable point and configurable coordinate/address storage.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/mappickerfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/mappickerfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/mappickerfield",
      "headline": "MapPickerField",
      "description": "Select one location with address search, a draggable point and configurable coordinate/address storage.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/mappickerfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/map-picker-structured-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/map-picker-structured-motion.mp4#video",
      "name": "MapPickerField — FLEX Fields demonstration",
      "description": "A recorded demonstration of MapPickerField in the FLEX Fields playground for Filament. Select one location with address search, a draggable point and configurable coordinate/address storage.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/map-picker-structured.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT12.633S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/map-picker-structured-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/mappickerfield#article"
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
  src="/art/previews/map-picker-structured-motion.mp4"
  poster="/art/previews/map-picker-structured.webp"
  width="1920"
  height="1080"
  aria-label="MapPickerField — FLEX Fields demonstration"
>
  <a href="/art/previews/map-picker-structured-motion.mp4">Watch the MapPickerField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Shared map settings

See [shared map configuration](/docs/location-v4) for Mapbox/MapLibre rendering, address-search endpoints, GPS, controls and appearance. Collection editing has separate guides for [markers](/docs/map-markers), [zones](/docs/map-zones) and [mixed geometry](/docs/map-editor).

### Summary

Select one location through address search or a point on the map. Store a structured coordinate/address array or a formatted address string. Rendering supports Mapbox and MapLibre; address lookup uses the configured geocoder.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField` |
| **State type (internal)** | Canonical array with keys from `ALL_FIELDS` |
| **Dehydrated state** | Subset of fields per `fields()`, or formatted string per `storeFormat()` |
| **FieldType** | `map_picker` |
| **Playground** | `map-picker` slug in Flex Fields playground |

The default Mapbox renderer requires a Mapbox access token in `config('filament-flex-fields.mapbox.access_token')` or per-field via `mapboxToken()`. `mapProvider('maplibre')` does not require that token for rendering; search remains a separate provider.

Server-side address search and reverse geocoding use automatically registered,
authenticated endpoints shared with Address Autocomplete. See
[shared endpoint configuration](/docs/location-v4#shared-address-search-endpoints)
for the route prefix and authentication guard.

---

### Basic usage

#### Structured Location (Coordinates + Address)
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;

MapPickerField::make('location')
    ->label('Office address')
    ->fields(['lat', 'lng', 'street', 'city', 'postcode', 'country', 'place_name'])
    ->requiredFields(['lat', 'lng', 'city'])
    ->streetAddressesOnly()
    ->defaultCenter([52.2297, 21.0122])
    ->defaultZoom(12);
```

#### Formatted String Output
```php
MapPickerField::make('venue')
    ->storeFormat(MapPickerField::STORE_STRING)
    ->stringFormat('{street}, {city} ({country})')
    ->countries(['PL', 'DE']);
```

---

### State & validation

#### Canonical internal keys
`lat`, `lng`, `street`, `city`, `region`, `postcode`, `country`, `country_name`, `place_name`

#### Stored value
| `storeFormat` | Dehydrated value |
|---------------|------------------|
| `structured` (default) | Array with only keys from `fields()` |
| `string` | Single string from `stringFormat()` placeholders `{field}` |

#### Validation rules (built-in)
| Behaviour | Detail |
|-----------|--------|
| `required()` | At least one configured field must have a value |
| `requiredFields()` | Listed fields must be filled when any value present |
| Latitude/Longitude | Automatically validated when present in `fields()` |
| `streetAddressesOnly()` | Ensures a full street address is selected; area-level results are rejected |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `fields(array $fields)` | Setup | default | Which address parts to store and show |
| `storeFormat(string $format)` | Setup | `structured` | `structured` or `string` |
| `stringFormat(string $format)` | Setup | `{place_name}` | Template for string storage |
| `requiredFields(array $fields)` | Setup | `[]` | Fields required when partially filled |
| `mapboxToken(string $token)` | Setup | config | Override Mapbox access token |
| `defaultCenter(array $center)` | Setup | Warsaw | `[latitude, longitude]` |
| `defaultZoom(int $zoom)` | Setup | `12` | Initial map zoom `1`–`22` |
| `searchable(bool $condition)` | Setup | `true` | Show address search box |
| `countries(array $countries)` | Setup | `null` | Restrict results to ISO country codes |
| `streetAddressesOnly(bool $cond)` | Setup | `false` | Restrict to full street addresses only |
| `searchTypes(array $types)` | Setup | `null` | Limit results to specific place types |
| `language(string\|null $language)` | Setup | config / locale | Preferred results language (`null` = auto) |
| `minSearchLength(int $length)` | Setup | `2` | Characters before search fires |
| `searchDebounce(int $ms)` | Setup | `350` | Debounce after typing |
| `readOnly(bool $condition)` | Setup | `false` | Disable map interaction |

> **POI note:** `searchTypes([MapboxSearchType::Poi])` returns general Mapbox points of interest — not restaurant/cafe category filters (Geocoding v5 limitation).

---

### FlexField / Flex Forms schema config

Shared with [AddressAutocompleteField](/docs/addressautocompletefield) via `AppliesGeocodingStudioConfig` / `GeocodingSearchScope`:

| Config key | Behaviour |
|------------|-----------|
| `layout` | Studio: `map` (default) or `input` (switches block to address autocomplete) |
| `search_scope` | Preset: `all`, `address`, `cities`, `regions`, `countries`, `admin`, `poi`, `custom` |
| `search_area` + `countries` | Global vs ISO whitelist |
| `language_mode` + `language` | Auto locale vs manual code |
| `default_center` / `default_zoom` | Map-only defaults |
| `search_types` / `street_addresses_only` | Applied when `search_scope=custom` |

---

### Real-world examples

#### Pickup Point (POI only)
```php
use Bjanczak\FilamentFlexFields\Enums\MapboxSearchType;

MapPickerField::make('pickup_point')
    ->searchTypes([MapboxSearchType::Poi])
    ->fields(['place_name', 'lat', 'lng'])
    ->required();
```

#### City Selector
```php
MapPickerField::make('city')
    ->searchTypes([MapboxSearchType::Place])
    ->fields(['city', 'country'])
    ->storeFormat('string')
    ->stringFormat('{city}, {country}');
```

---

### Playground

`/admin/flex-fields/map-picker`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [AddressAutocompleteField](/docs/addressautocompletefield) | Address search without a map |
| [CountryField](/docs/countryfield) | Simple country dropdown |
| [FlexTextInput](/docs/flextextinput) | Manual address entry |

### Building numbers and map gestures

```php
MapPickerField::make('delivery_address')
    ->requireStreetNumber()
    ->countries(['PL'])
    ->requiredFields(['street', 'lat', 'lng']);
```

`requireStreetNumber()` removes street-only results and requires a numbered
building address. This uses provider number metadata, applies to reverse lookup,
and is enforced again on the server. It does not verify an apartment or prove an
address is deliverable. The current geocoder uses Mapbox V5; Mapbox V6 supports
secondary/unit addresses in covered regions, which is a separate integration.

Use `scrollZoom()->cooperativeGestures(false)` to enable direct wheel/trackpad
zoom. Native SDK touch gestures handle pinch/rotation. Keeping cooperative gestures
on requires the SDK modifier/multiple-finger gesture to protect normal page scroll.

### Specialized map components

| Component | Use it for |
| --- | --- |
| [MapCoordinatesField](/docs/map-coordinates) | One coordinate pair without geocoding. |
| [MapDisplayField](/docs/map-display) | Supplied layers, feature cards and coverage legends. |
| [MapMarkersField](/docs/map-markers) | Multiple points with optional clustering. |
| [MapZonesField](/docs/map-zones) | Editable polygons, rectangles and circles. |
| [MapEditorField](/docs/map-editor) | Points, lines and polygons in one collection. |
| [CountryMapField](/docs/country-map-field) | ISO country selection on an SVG world map. |
