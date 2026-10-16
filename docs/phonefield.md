---
title: "PhoneField"
description: "Collect international phone numbers with a searchable country selector, local flags, dial codes, normalization and PHP validation."
icon: "phone"
"canonical": "https://flex-fields.bjanczak.com/docs/phonefield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/phone-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "PhoneField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/phone-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/phone-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/phone-field.webp"
"twitter:image:alt": "PhoneField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/phonefield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/phonefield",
      "name": "PhoneField",
      "description": "Collect international phone numbers with a searchable country selector, local flags, dial codes, normalization and PHP validation.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/phonefield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/phonefield#article",
      "url": "https://flex-fields.bjanczak.com/docs/phonefield",
      "headline": "PhoneField",
      "description": "Collect international phone numbers with a searchable country selector, local flags, dial codes, normalization and PHP validation.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/phonefield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/phone-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/phone-field-motion.mp4#video",
      "name": "PhoneField — FLEX Fields demonstration",
      "description": "A recorded demonstration of PhoneField in the FLEX Fields playground for Filament. Collect international phone numbers with a searchable country selector, local flags, dial codes, normalization and PHP validation.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/phone-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT10.917S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/phone-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/phonefield#article"
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
  src="/art/previews/phone-field-motion.mp4"
  poster="/art/previews/phone-field.webp"
  width="1920"
  height="1080"
  aria-label="PhoneField — FLEX Fields demonstration"
>
  <a href="/art/previews/phone-field-motion.mp4">Watch the PhoneField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)


### Summary

International phone input with searchable country picker, local circle flags, libphonenumber validation, and structured state (`country`, `national`, `e164`).

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField` |
| **State type** | `array&lt;country: string, national: string, e164: string&gt;` |
| **FieldType** | `phone` |
| **Playground** | `phone-field` slug in Flex Fields playground |

### Basic usage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;

PhoneField::make('phone')
    ->label('Mobile number')
    ->defaultCountry('PL')
    ->required();

PhoneField::make('contact_phone')
    ->countries(['PL', 'DE', 'GB', 'US'])
    ->mobileOnly()
    ->browserLocaleDefault()
    ->browserLocaleSortFirst();
```

Load from an E.164 string on edit:

```php
PhoneField::make('phone')
    ->afterStateHydrated(function (PhoneField $component, mixed $state): void {
        if (is_string($state) && filled($state)) {
            $component->state($component->normalizeState($state));
        }
    });
```

Store only E.164 in the database:

```php
PhoneField::make('phone')
    ->dehydrateStateUsing(fn (array $state): ?string => filled($state['e164'] ?? null)
        ? $state['e164']
        : null);
```

To store the default composite value, use a JSON column with an Eloquent `array`
cast. Its `country` key is a code such as `PL` or `DE`, never the country label.
Country names, flag URLs and dropdown descriptions are not persisted. Saving only
E.164 is optional, using the dehydration callback above.

### State format

| Key | Description | Example |
|-----|-------------|---------|
| `country` | Uppercase telephone region code (ISO alpha-2, or supported `AC` / `TA` / `XK`) | `PL` |
| `national` | After a successful `normalizeState()`, libphonenumber **NATIONAL** formatting by default (spaces / punctuation). Opt-in `nationalDigitsOnly()` / `nationalFormat('digits')` stores digits only. The Alpine as-you-type UI keeps digits internally and formats for display. | `512 345 678` or `512345678` |
| `e164` | E.164 when the number is valid; otherwise may be empty or a provisional dial+digits string | `+48512345678` |
| `international` / `rfc3966` | Optional — when `includeFormats([...])` is enabled | `+48 512 345 678`, `tel:+48-512-345-678` |
| `carrier` / `geo` / `timezones` / `type` | Optional — when `includeMetadata([...])` is enabled (PHP libphonenumber mappers; zero JS cost) | `Orange`, `Poland`, `["Europe/Warsaw"]`, `MOBILE` |

On hydrate and dehydrate, `normalizeState()` runs automatically. A plain `string` state (e.g. `+48 512 345 678`) is parsed on hydrate. Default `national` stays NATIONAL-formatted for BC; use digits-only or `e164` when you need machine-friendly values.

### Validation

| Behaviour | Detail |
|-----------|--------|
| Built-in | Checks submitted region and number shape before normalization, then validates with libphonenumber |
| `required()` | `national` must not be empty |
| `mobileOnly()` | Sugar for `allowTypes([MOBILE])` (also accepts `FIXED_LINE_OR_MOBILE` unless `strictTypes()`) |
| `fixedLineOnly()` | Sugar for `allowTypes([FIXED_LINE])` |
| `allowTypes([...])` | Explicit libphonenumber types (`MOBILE`, `VOIP`, …) |
| `validateForRegion()` | `isValidNumberForRegion` against the selected country |
| Filament `required` rule | Overridden to `nullable` — validation handled by custom rule |

> Do not combine `mobileOnly()` and `fixedLineOnly()` on the same field — throws `InvalidArgumentException`. Prefer `allowTypes()` for multi-type rules.

### Configuration API

#### `variant(string|Closure $variant)`


Visual style shared with FlexTextInput. Values: `primary` (default), `secondary`, `flat`, `soft`.

```php
->variant('secondary')
```

#### `size(string|ControlSize|Closure $size)`


Control height. See [Control size](/docs/configuration). Default: `md`.

```php
PhoneField::make('field_name')
    ->size('md');
```
#### `defaultCountry(string|Closure $countryCode)`


ISO country code when no country is selected. Default: `PL`. Falls back to first allowed country or `US`.

```php
PhoneField::make('field_name')
    ->defaultCountry('value');
```
#### `countries(array|Closure|null $countries)`


Whitelist of ISO codes. `null` = all countries (minus `exceptCountries`).

```php
->countries(['PL', 'DE', 'FR'])
```

#### `exceptCountries(array|Closure $countries)`


Blacklist applied after the whitelist. Default: `[]`.

```php
PhoneField::make('field_name')
    ->exceptCountries(['value1', 'value2']);
```
#### `searchable(bool|Closure $condition = true)`


Show search input in the country dropdown. Default: `true`.

```php
PhoneField::make('field_name')
    ->searchable(true);
```
#### `suffixIcon(string|BackedEnum|Htmlable|Closure|bool|null $icon = null)`


Trailing icon. Pass `false` to hide. Pass an icon name to set a custom icon. Default: config `filament-flex-fields.ui.phone_suffix_icon` or `GravityIcon::Smartphone`.

```php
->suffixIcon(false)
->suffixIcon('heroicon-o-device-phone-mobile')
```

#### `internationalPrefix(bool|Closure $condition = true)`


Show dial code prefix next to the national input. Default: `true`.

```php
PhoneField::make('field_name')
    ->internationalPrefix(true);
```
#### `mobileOnly(bool|Closure $condition = true)`


Restrict to mobile numbers.

```php
PhoneField::make('field_name')
    ->mobileOnly(true);
```
#### `fixedLineOnly(bool|Closure $condition = true)`


Restrict to fixed-line numbers.

```php
PhoneField::make('field_name')
    ->fixedLineOnly(true);
```
#### `browserLocaleDefault(bool|Closure $condition = true)`


When enabled and national number is empty, pre-select country from `Accept-Language` / browser locale.

```php
PhoneField::make('field_name')
    ->browserLocaleDefault(true);
```
#### `browserLocaleSortFirst(bool|Closure $condition = true)`


Sort country list with browser locale country first.

```php
PhoneField::make('field_name')
    ->browserLocaleSortFirst(true);
```
#### `locale(string|Closure|null $locale)`


Language for country names in the picker (`en` → Poland, `pl` → Polska). Defaults to `app()->getLocale()`. CountryField and PhoneField share the same translation resolver: package overrides first, then PHP ICU regional names, then English fallback. Names are identical on the initial render and in the dropdown.

```php
PhoneField::make('phone')->locale('pl');
```
#### `placeholder(string|Closure|null $placeholder)`

Inherited from Filament `HasPlaceholder`. When omitted, the field uses a libphonenumber national example for the default country (and mobile/fixed type when constrained), otherwise the package language string.

```php
PhoneField::make('field_name')
    ->placeholder('Enter value...');
```
#### `allowTypes(array|Closure|null $types)`

Restrict accepted libphonenumber number types (`PhoneNumberType` cases or names like `MOBILE`, `VOIP`). When set, overrides `mobileOnly()` / `fixedLineOnly()` sugar.

```php
PhoneField::make('phone')->allowTypes(['MOBILE', 'VOIP']);
```

#### `strictTypes(bool|Closure $condition = true)`

Do not auto-accept `FIXED_LINE_OR_MOBILE` alongside `MOBILE` / `FIXED_LINE`.

#### `validateForRegion(bool|Closure $condition = true)`

Require `isValidNumberForRegion()` for the selected country (stricter than global `isValidNumber`).

#### `nationalFormat(PhoneNationalFormat|string|Closure $format)` / `nationalDigitsOnly()`

Default `national` (BC). Digits-only is opt-in:

```php
PhoneField::make('phone')->nationalDigitsOnly();
```

#### `includeFormats(array|Closure $formats)`

Add `international` and/or `rfc3966` keys to dehydrated state (valid numbers only).

#### `includeMetadata(array|Closure $metadata)`

Add PHP-side `carrier`, `geo`, `timezones`, and/or `type` keys (valid numbers only). No JS / phone-lib budget impact.

#### `readOnly(bool|Closure $condition = true)`


Inherited from Filament `CanBeReadOnly`.

```php
PhoneField::make('field_name')
    ->readOnly(true);
```
#### `focusOutline(bool|Closure $condition = true)`


Inherited from `HasFieldFocusOutline`.

```php
PhoneField::make('field_name')
    ->focusOutline(true);
```

### Public helper methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getVariant()` | `string` | Resolved variant |
| `getAllowedCountryCodes()` | `list&lt;string&gt;\|null` | Whitelist or `null` |
| `getExceptCountryCodes()` | `list&lt;string&gt;` | Blacklist |
| `getDefaultCountryCode()` | `string` | Effective default region |
| `isSearchable()` | `bool` | Country search enabled |
| `hasSuffixIcon()` | `bool` | Suffix icon visible |
| `showsInternationalPrefix()` | `bool` | Dial prefix visible |
| `isMobileOnly()` | `bool` | Mobile validation |
| `isFixedLineOnly()` | `bool` | Fixed-line validation |
| `shouldUseBrowserLocaleDefault()` | `bool` | Browser locale default |
| `shouldSortCountriesByBrowserLocale()` | `bool` | Browser locale sort |
| `getBrowserLocaleCountryCode()` | `string\|null` | Detected locale country |
| `getCountriesMetadata()` | `list&lt;array&gt;` | `code`, `name`, `dial_code`, `flag_url` |
| `getCountrySelectOptions()` | `array` | Options for internal select |
| `getDefaultSuffixIcon()` | `string\|BackedEnum\|Htmlable` | Default suffix icon |
| `getSuffixIcon()` | `string\|BackedEnum\|Htmlable\|null` | Resolved suffix icon |
| `normalizeState(mixed $state)` | `array` | Canonical `{country, national, e164}` |
| `getPhoneValidationMessage(array $state)` | `string\|null` | Error message or `null` |
| `getWrapperClasses()` | `list&lt;string&gt;` | CSS class list |

### FlexField schema config

| Config key | Maps to |
|------------|---------|
| `size` | `size()` |
| `variant` | `variant()` |
| `default_country` | `defaultCountry()` |
| `countries` | `countries()` |
| `except_countries` | `exceptCountries()` |
| `searchable` | `searchable()` |
| `suffix_icon` | `suffixIcon()` |
| `international_prefix` | `internationalPrefix()` |
| `mobile_only` | `mobileOnly()` |
| `fixed_line_only` | `fixedLineOnly()` |
| `allow_types` | `allowTypes()` |
| `strict_types` | `strictTypes()` |
| `validate_for_region` | `validateForRegion()` |
| `national_format` | `nationalFormat()` / `nationalDigitsOnly()` |
| `include_formats` | `includeFormats()` |
| `include_metadata` | `includeMetadata()` |
| `browser_locale_default` | `browserLocaleDefault()` |
| `browser_locale_sort_first` | `browserLocaleSortFirst()` |
| `locale` | `locale()` |

### CSS classes

| Class | Role |
|-------|------|
| `fff-phone-field` | Root wrapper |
| `fff-phone-field--{sm\|md\|lg}` | Size modifier |
| `fff-phone-field__country-trigger` | Country picker button |
| `fff-phone-field__country-menu` | Teleported dropdown (`is-positioned` when open) |
| `fff-phone-field__control` | National number input area |
| `fff-phone-field__dial-prefix` | International prefix display |

Shares FlexTextInput shell classes (`fff-flex-text-input-field`, variant modifiers).

### Implementation notes

- Country dropdown uses `x-teleport="body"` to avoid overflow clipping.
- Depends on `giggsey/libphonenumber-for-php` for parsing and validation.
- Empty national number dehydrates to `e164: ''` regardless of partial dial prefix.

---

### Playground

Slug: **`phone-field`**

| Demo field | Shows |
|------------|-------|
| Default | Searchable country picker, E.164 validation |
| Restricted countries | Whitelist + mobile-only |
| Browser locale | `browserLocaleDefault()` + sort-first |
| Fixed line only | `fixedLineOnly()` |
| Except countries | `exceptCountries()` |
| Non-searchable / read-only | Full list `searchable(false)`, `readOnly()` |
| Digits / region / enriched | `nationalDigitsOnly()`, `validateForRegion()`, formats + metadata |
| Allow types | `allowTypes(['VOIP','MOBILE'])` |

`/admin/flex-fields/phone-field` — see [Playground](/docs/index#playground).

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [CountryField](/docs/countryfield) | ISO country only — no phone number |
| [FlexTextInput](/docs/flextextinput) | Plain text without libphonenumber |
| [FlexVerificationCode](/docs/flexverificationcode) | OTP / SMS codes, not full numbers |
| [AddressAutocompleteField](/docs/addressautocompletefield) | Structured address with optional phone |

---

### Country catalog and local flags

The country picker follows libphonenumber's supported telephone regions, including
`AC`, `TA` and `XK`. It is intentionally separate from CountryField's default 249-entry
ISO address catalog: some ISO territories do not have a supported calling-code region.
The exact telephone list follows the installed libphonenumber metadata version.
`countries()` and `exceptCountries()` constrain both selection and locale detection.

Both pickers share one `country-flags` asset publication. All supported flags are
materialized local SVG files, including territories whose upstream files are aliases.
No external flag CDN is contacted. Publish them through the standard installation steps:

```bash
php artisan filament:assets
php artisan fff:assets:publish
```

See [Installation](/docs/installation) for deployment and asset checks. Country names
and metadata are cached within the request; no browser-side country-name library or
full flag preloading is required.

The current installed libphonenumber metadata contains **245 telephone regions**,
while CountryField defaults to **249 ISO entries**. Telephone regions are not a
superset of the address catalog. Every supported region has a local flag and a
calling prefix from libphonenumber, including shared prefixes such as `+1`.
The prefix displayed by PhoneField follows the selected region.

### Submitted state and server validation

Save through Filament's validated `getState()` result. Validation rejects unknown
or excluded region codes, country labels, structured country values, non-string
national numbers, unsupported characters and excessive input. Malformed state
can be rendered safely without raising a server error, but cannot pass validation.
`required()` also rejects an empty national number.

The server derives E.164 from `country` and `national`; a forged client-side `e164`
cannot override it. Unrequested keys such as labels, flag URLs or enrichment are
removed on dehydration. Enabled `includeFormats()` / `includeMetadata()` values
are computed on the server and retained; submitted enrichment is never trusted.
`validateForRegion()` additionally requires the parsed number to be valid for the
selected region. This remains optional because several regions share calling codes.
