---
title: "CountryField"
description: Searchable country picker with circle flags and ISO 3166-1 alpha-2 code storage.
icon: "flag"
"canonical": "https://flex-fields.bjanczak.com/docs/countryfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/country-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "CountryField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/country-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/country-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/country-field.webp"
"twitter:image:alt": "CountryField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/countryfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/countryfield",
      "name": "CountryField",
      "description": "Searchable country picker with circle flags and ISO 3166-1 alpha-2 code storage.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/countryfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/countryfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/countryfield",
      "headline": "CountryField",
      "description": "Searchable country picker with circle flags and ISO 3166-1 alpha-2 code storage.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/countryfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/country-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/country-field-motion.mp4#video",
      "name": "CountryField — FLEX Fields demonstration",
      "description": "A recorded demonstration of CountryField in the FLEX Fields playground for Filament. Searchable country picker with circle flags and ISO 3166-1 alpha-2 code storage.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/country-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.367S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/country-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/countryfield#article"
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
  src="/art/previews/country-field-motion.mp4"
  poster="/art/previews/country-field.webp"
  width="1920"
  height="1080"
  aria-label="CountryField — FLEX Fields demonstration"
>
  <a href="/art/previews/country-field-motion.mp4">Watch the CountryField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Searchable country picker with circle flags. Stores a single **ISO 3166-1 alpha-2** code. Offers the 249 officially assigned ISO 3166-1 entries by default, including dependent territories. Add non-ISO regions explicitly when your application needs them. Flags are local SVG assets shared with PhoneField.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField` |
| **State type** | `string\|null` — ISO alpha-2 country code |
| **Model cast** | `'country_code' => 'string'` |
| **FieldType** | `country` |
| **Playground** | `country-field` slug in Flex Fields playground |

---

### Basic usage

#### Standard country picker

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;

CountryField::make('country')
    ->label('Country')
    ->defaultCountry('PL')
    ->required();
```

#### Whitelist and browser detection

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;

CountryField::make('shipping_country')
    ->countries(['PL', 'DE', 'GB', 'US', 'FR'])
    ->exceptCountries(['RU'])
    ->showCountryCode()
    ->browserLocaleDefault()
    ->browserLocaleSortFirst();
```

---

### State & validation

#### Stored value

State is a single **uppercase two-letter country or territory code**. The default catalog accepts ISO 3166-1 codes; explicitly enabled non-ISO regions are also accepted.

```php
$record->country_code; // string|null — e.g. "PL", "US", "DE"
```

Country labels, dialing descriptions and flag URLs are presentation metadata.
They are never included in the field's dehydrated value. Save the form's validated
`getState()` result to a string column; a JSON cast is unnecessary for this field.

#### Validation rules

Built-in validation ensures the submitted code is in the resolved country list.

| Rule | Detail |
|------|--------|
| `nullable` | Always (unless `required()`) |
| `in(...)` | Value must belong to the resolved catalog, including explicitly enabled additional regions |

---

Validation runs against the submitted value before display normalization. Labels
such as `Poland`, unknown codes, excluded markets, numeric values and arrays are
rejected. `required()` rejects an empty value. Hydration can normalize an existing
lowercase code, but the browser submits uppercase identities. These rules apply
when saving through Filament's validated form state.

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `countries(array\|Closure\|null $countries)` | Setup | `null` | Whitelist of ISO alpha-2 codes |
| `additionalRegions(array\|Closure $regions)` | Setup | `[]` | Add selected non-ISO regions: `AC`, `DG`, `EA`, `IC`, `TA`, `XK` |
| `exceptCountries(array\|Closure $countries)` | Setup | `[]` | Blacklist of ISO alpha-2 codes |
| `defaultCountry(string\|Closure\|null $countryCode)` | Setup | `null` | Fallback ISO code |
| `browserLocaleDefault(bool\|Closure $condition = true)` | Setup | `false` | Pre-select from browser locale |
| `browserLocaleSortFirst(bool\|Closure $condition = true)` | Setup | `false` | Sort browser country to top of list |
| `locale(string\|Closure\|null $locale)` | Setup | `app()->getLocale()` | Language for country names (`en` → Poland, `pl` → Polska) |
| `showCountryCode(bool\|Closure $condition = true)` | Setup | `false` | Show ISO code (e.g. `PL`) next to name |
| `showDialCode(bool\|Closure $condition = true)` | Setup | `false` | Show international dial code (e.g. `+48`) |
| `searchable(bool\|Closure $condition = true)` | Setup | `true` | Show search input in dropdown |
| `variant(string\|Closure $variant)` | Setup | `'primary'` | Visual style: `primary`, `secondary`, `flat`, `soft` |
| `size(string\|ControlSize\|Closure $size)` | Setup | `'md'` | Control size: `sm`, `md`, `lg` |
| `rounding(string\|Closure\|null $rounding)` | Setup | config | Border radius token |

#### `countries()` / `exceptCountries()`

Limit the list to specific markets:

```php
CountryField::make('country')
    ->countries(['US', 'CA', 'MX']);
```

#### `showDialCode()`

Useful for address forms where phone context is helpful:

```php
CountryField::make('country')->showDialCode();
```

---

### Real-world examples

#### Shipping address form

```php
CountryField::make('shipping_country')
    ->label('Delivery country')
    ->countries(config('app.shipping_countries'))
    ->browserLocaleDefault()
    ->required();
```

#### ISO + Kosovo

```php
CountryField::make('country')->additionalRegions(['XK']); // 250 entries

CountryField::make('country')
    ->additionalRegions(['XK'])
    ->countries(['PL', 'DE', 'XK']); // three allowed entries
```

`XK` is user-assigned, not an officially assigned ISO country code. The other available
extensions (`AC`, `DG`, `EA`, `IC`, `TA`) are also excluded by default. Adding a region
does not bypass `countries()` or `exceptCountries()`. Unknown codes remain excluded.
Applications migrating from the former 255-entry list can opt into all six regions:

```php
CountryField::make('country')->additionalRegions(['AC', 'DG', 'EA', 'IC', 'TA', 'XK']);
```

PhoneField uses libphonenumber's supported telephone regions, independently of this
address-country catalog. An ISO territory without a calling-code region can still
be selected in CountryField; `showDialCode()` leaves its dialing description empty.

#### Multi-language support

Country names follow `app()->getLocale()` by default (`Poland` / `Polska`) from package translation overrides, then PHP ICU regional names. English names are a final fallback when ICU is unavailable. Both the initial render and the dropdown use the same names. Pin a language per field with `locale()`, independently of the panel locale:

```php
CountryField::make('country')->locale('pl');
CountryField::make('country')->locale(fn (): string => auth()->user()->locale);
```

---

### Playground

`/admin/flex-fields/country-field`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [PhoneField](/docs/phonefield) | For picking a country as part of a phone number input |
| [TimezoneField](/docs/timezonefield) | For picking a timezone instead of a country |
| [AddressAutocompleteField](/docs/addressautocompletefield) | For full address search with Mapbox |

---

### Performance

| Mechanism | What it does |
|-----------|--------------|
| **Local flags** | CountryField and PhoneField compose the same `country-flags` asset owner; no external flag requests |
| **Memoized Metadata** | Country lists and translations are cached per-request |
| **Teleport** | Uses `x-teleport="body"` to avoid overflow clipping in modals |

Flags are installed with the package and published through the regular FLEX asset
pipeline. Use the same deployment steps described in [Installation](/docs/installation):

```bash
php artisan filament:assets
php artisan fff:assets:publish
```

No extra npm package or browser-side translation library is needed. Each flag uses a
versioned local URL; opening the picker does not fetch the full flag collection.
The circular artwork is from [HatScripts/circle-flags](https://github.com/HatScripts/circle-flags),
pinned to a recorded commit with the MIT license retained in the package.

When using the JSON/schema builder, configure `additional_regions` with the same
list, e.g. `['additional_regions' => ['XK']]`. Direct CountryField instances have
no selected country by default; the schema builder's default configuration uses `PL`.

In the dropdown, rows with only a country name use the same flag size as PhoneField.
Rows with a name and a second-line code or dialing description use a slightly
larger flag, scaled for the field's `sm`, `md` or `lg` size. PhoneField's compact
flag sizing is unchanged.
