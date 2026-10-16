---
title: "AddressAutocompleteField"
description: Mapbox-powered address search without a map — combobox autocomplete with structured or string storage.
icon: "map-pin-house"
"canonical": "https://flex-fields.bjanczak.com/docs/addressautocompletefield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/address-autocomplete.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "AddressAutocompleteField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/address-autocomplete-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/address-autocomplete-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/address-autocomplete.webp"
"twitter:image:alt": "AddressAutocompleteField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/addressautocompletefield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/addressautocompletefield",
      "name": "AddressAutocompleteField",
      "description": "Mapbox-powered address search without a map — combobox autocomplete with structured or string storage.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/addressautocompletefield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/addressautocompletefield#article",
      "url": "https://flex-fields.bjanczak.com/docs/addressautocompletefield",
      "headline": "AddressAutocompleteField",
      "description": "Mapbox-powered address search without a map — combobox autocomplete with structured or string storage.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/addressautocompletefield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/address-autocomplete-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/address-autocomplete-motion.mp4#video",
      "name": "AddressAutocompleteField — FLEX Fields demonstration",
      "description": "A recorded demonstration of AddressAutocompleteField in the FLEX Fields playground for Filament. Mapbox-powered address search without a map — combobox autocomplete with structured or string storage.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/address-autocomplete.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT13.7S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/address-autocomplete-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/addressautocompletefield#article"
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
  src="/art/previews/address-autocomplete-motion.mp4"
  poster="/art/previews/address-autocomplete.webp"
  width="1920"
  height="1080"
  aria-label="AddressAutocompleteField — FLEX Fields demonstration"
>
  <a href="/art/previews/address-autocomplete-motion.mp4">Watch the AddressAutocompleteField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Shared location settings

Address search shares its provider and authenticated endpoints with [MapPickerField](/docs/mappickerfield). See [shared map configuration](/docs/location-v4#shared-address-search-endpoints) for routing, authentication and proxy settings.

### Summary

Mapbox-powered **address search** without a map — combobox autocomplete with structured or string storage. Shares geocoding logic with `MapPickerField` but omits coordinates by default. On phones and tablets, suggestions open as a **bottom sheet** with an in-sheet search field (same overlay stack as SelectField) instead of a desktop dropdown.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField` |
| **State type** | `array` — canonical address structure |
| **Model cast** | `'address' => 'array'` · `'address' => 'string'` |
| **FieldType** | `address_autocomplete` |
| **Playground** | `address-autocomplete` slug in Flex Fields playground |

Requires Mapbox token in `config('filament-flex-fields.mapbox.access_token')` or per-field `mapboxToken()`.

The default server proxy shares its authenticated endpoints with Map Picker,
without loading a map SDK. See
[shared endpoint configuration](/docs/location-v4#shared-address-search-endpoints)
to configure the prefix and authentication guard.

---

### Basic usage

#### Structured address storage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;

AddressAutocompleteField::make('delivery_address')
    ->label('Delivery address')
    ->fields(['street', 'city', 'postcode', 'country', 'place_name'])
    ->requiredFields(['city'])
    ->streetAddressesOnly()
    ->countries(['PL'])
    ->size('md')
    ->variant('primary');
```

#### String storage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;

AddressAutocompleteField::make('billing_city')
    ->storeFormat(AddressAutocompleteField::STORE_STRING)
    ->stringFormat('{city}, {country_name}')
    ->variant('secondary')
    ->size('sm');
```

---

### State & storage

#### Canonical keys

The field uses a standard set of keys for internal state:
`street`, `city`, `region`, `postcode`, `country`, `country_name`, `place_name`.

#### Storage formats

| `storeFormat` | Dehydrated value |
|---------------|------------------|
| `structured` (default) | Array with only keys from `fields()` |
| `string` | Single string from `stringFormat()` placeholders `{field}` |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `fields(array\|Closure $fields)` | Setup | all | Address components to store in the array |
| `storeFormat(string\|Closure $format)` | Setup | `'structured'` | `structured` or `string` |
| `stringFormat(string\|Closure $format)` | Setup | auto | Template for string storage (e.g. `{street}, {city}`) |
| `requiredFields(array\|Closure $fields)` | Setup | `[]` | Fields that must be present in the result |
| `mapboxToken(string\|Closure\|null $token)` | Setup | config | Mapbox API access token |
| `countries(array\|Closure\|null $countries)` | Setup | `null` | Whitelist of ISO country codes |
| `streetAddressesOnly(bool\|Closure $condition = true)` | Setup | `false` | Reject results without a house number |
| `searchTypes(array\|Closure\|null $types)` | Setup | `null` | Limit results to types (e.g. `poi`, `place`) |
| `minSearchLength(int\|Closure $length)` | Setup | `2` | Minimum characters to trigger search |
| `searchDebounce(int\|Closure $ms)` | Setup | `350` | Milliseconds to wait after typing |
| `language(string\|Closure\|null $language)` | Setup | config | Preferred results language |
| `prefixIcon(...)` | Setup | `MapPin` | Custom leading icon |
| `clearIcon(...)` | Setup | `CircleXmark` | Custom clear button icon |
| `variant(string\|Closure $variant)` | Setup | `'primary'` | Visual style: `primary`, `secondary`, `flat` |
| `size(string\|ControlSize\|Closure $size)` | Setup | `'md'` | Control size: `sm`, `md`, `lg` |
| `rounding(string\|Closure\|null $rounding)` | Setup | config | Border radius token |

#### `searchTypes()`

Limit results to specific Mapbox place types:

```php
use Bjanczak\FilamentFlexFields\Enums\MapboxSearchType;

AddressAutocompleteField::make('venue')
    ->searchTypes([MapboxSearchType::Poi]);
```

Supported type strings / enum cases: `country`, `region`, `postcode`, `district`, `place`, `locality`, `neighborhood`, `address`, `poi`.

> **Note:** Mapbox Geocoding v5 treats `poi` as general points of interest. It does **not** filter by category (restaurant vs cafe). Category search requires Mapbox Search Box (not wired in this field).

#### `language()`

Preferred language for result labels. When `null` (or omitted), the field falls back to `config('filament-flex-fields.mapbox.default_language')`, then `app()->getLocale()`.

---

### FlexField / Flex Forms schema config

When building via `FlexFieldFormBuilder` / Flex Forms Studio, these config keys map onto the fluent API (see also `GeocodingSearchScope` presets):

| Config key | Maps to / behaviour |
|------------|---------------------|
| `search_scope` | Preset → `searchTypes()` + `streetAddressesOnly()`. Values: `all`, `address`, `cities`, `regions`, `countries`, `admin`, `poi`, `custom` |
| `search_types` | Used when `search_scope=custom` — raw Mapbox types list |
| `street_addresses_only` | Used when `search_scope=custom` |
| `search_area` | `global` clears country filter; `countries` applies `countries` |
| `countries` | ISO codes when `search_area=countries` (empty = worldwide) |
| `language_mode` | `auto` → `language(null)`; `manual` → `language` |
| `language` | BCP 47 / ISO code when `language_mode=manual` |
| `layout` | Studio-only: `input` vs `map` (switches to `map_picker`) |
| `min_search_length` / `search_debounce` | Search UX |
| `fields` / `store_format` / `string_format` / `required_fields` / `searchable` / `size` / `variant` | Standard field API |

`GeocodingSearchScope` enum (`Bjanczak\FilamentFlexFields\Enums\GeocodingSearchScope`) is the shared preset map used by Address Autocomplete and Map Picker configurators.

---

### Real-world examples

#### Simple city picker

```php
AddressAutocompleteField::make('city')
    ->searchTypes(['place'])
    ->fields(['city', 'country_name'])
    ->storeFormat('string')
    ->stringFormat('{city}, {country_name}');
```

#### Full shipping address

```php
AddressAutocompleteField::make('shipping_address')
    ->streetAddressesOnly()
    ->fields(['street', 'city', 'postcode', 'country'])
    ->requiredFields(['street', 'city', 'postcode'])
    ->countries(['US', 'CA']);
```

---

### Shared search and editable addresses

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;

AddressAutocompleteField::make('address')
    ->countries(['PL'])
    ->searchLimit(8)
    ->searchBounds([14, 49, 24.2, 55])
    ->searchProximity([21.0122, 52.2297])
    ->fields(['lat', 'lng', 'street', 'city', 'region', 'postcode', 'country', 'place_name'])
    ->editableAddress()
    ->editableAddressFields(['street', 'city', 'postcode', 'country'])
    ->requiredFields(['street', 'city', 'country']);
```

Both fields support `searchLimit()` (1–10), `searchBounds()` (nonwrapping bbox), `searchProximity()` ([lng,lat]), `geocodingEndpoints('/search', '/reverse')`, debounce/minimum search length, countries/types/language and existing storage policies. Search is bounded to 256 characters. Direct/proxy cache keys include search scope and limit. `countries(null)` is unrestricted; `countries([])` denies search rather than silently becoming unrestricted. The configured endpoint must be a same-origin path because requests carry session/CSRF information.

AddressAutocomplete keeps its existing default storage keys; `fields(['lat','lng', ...])` opts into coordinate storage. `addressDetails()` shows structured detail inputs; `editableAddress()` enables manual corrections; `editableAddressFields()` admits only street/city/region/postcode/country. Read-only detail inputs do not modify state. A manual address edit clears the old coordinates and result identity, avoiding a new text address paired with stale coordinates. Required keys must be present in the configured stored field set. Restricted nonempty addresses with country storage must include admitted country metadata; omitting it no longer bypasses validation.

---

### Playground

`/admin/flex-fields/address-autocomplete`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [MapPickerField](/docs/mappickerfield) | When you also need to store coordinates (lat/lng) |
| [CountryField](/docs/countryfield) | For picking a country only |
| [FlexTextInput](/docs/flextextinput) | For manual address entry without Mapbox |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-address-autocomplete-field` | Root wrapper |
| `fff-address-autocomplete__search-wrap` | Input + dropdown container |
| `fff-map-picker__dropdown-panel` | Suggestions list |
| `fff-address-autocomplete__selection-error` | Validation error overlay |

---

### Performance

| Mechanism | What it does |
|-----------|--------------|
| **Debounced Search** | Prevents excessive API calls while typing |
| **Lazy JS** | Mapbox geocoding logic is loaded only when needed |
| **Wire:ignore** | Uses `wire:ignore` to prevent Livewire from disrupting Alpine state |
