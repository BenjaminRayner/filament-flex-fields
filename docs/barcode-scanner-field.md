---
title: "Barcode scanner"
description: "Scan QR codes and product barcodes in Filament with camera switching, optional torch, server validation and a shared FLEX text input."
icon: "scan-barcode"
"canonical": "https://flex-fields.bjanczak.com/docs/barcode-scanner-field"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/barcode-scanner-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Barcode scanner in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/barcode-scanner-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/barcode-scanner-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/barcode-scanner-field.webp"
"twitter:image:alt": "Barcode scanner in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/barcode-scanner-field#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/barcode-scanner-field",
      "name": "Barcode scanner",
      "description": "Scan QR codes and product barcodes in Filament with camera switching, optional torch, server validation and a shared FLEX text input.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/barcode-scanner-field#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/barcode-scanner-field#article",
      "url": "https://flex-fields.bjanczak.com/docs/barcode-scanner-field",
      "headline": "Barcode scanner",
      "description": "Scan QR codes and product barcodes in Filament with camera switching, optional torch, server validation and a shared FLEX text input.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/barcode-scanner-field#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/barcode-scanner-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/barcode-scanner-field-motion.mp4#video",
      "name": "Barcode scanner — FLEX Fields demonstration",
      "description": "A recorded demonstration of Barcode scanner in the FLEX Fields playground for Filament. Scan QR codes and product barcodes in Filament with camera switching, optional torch, server validation and a shared FLEX text input.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/barcode-scanner-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT13S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/barcode-scanner-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/barcode-scanner-field#article"
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
  src="/art/previews/barcode-scanner-field-motion.mp4"
  poster="/art/previews/barcode-scanner-field.webp"
  width="1920"
  height="1080"
  aria-label="Barcode scanner — FLEX Fields demonstration"
>
  <a href="/art/previews/barcode-scanner-field-motion.mp4">Watch the Barcode scanner demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Scan a product, ticket or inventory label directly into your form. `BarcodeScannerField` combines the FLEX text input with a camera scanner that fills its viewing surface, floating controls and an optional confirmation sound.

<CardGroup cols={2}>
  <Card title="Camera and manual entry" icon="camera">Use the camera, type a code or connect a keyboard-style barcode reader.</Card>
  <Card title="Validate before saving" icon="shield-check">Restrict formats, verify EAN/UPC checksums and add your own business rules.</Card>
</CardGroup>

## Start with one field

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BarcodeScannerField;

BarcodeScannerField::make('sku')
    ->label('Product barcode')
    ->required();
```

The default value is a trimmed `string`, or `null` when empty. Save it in a string column. No media table or storage disk is required for scanning. For long QR payloads use a `text` column, or set `barcodeRule('max:255')` when your database column is limited to 255 characters.

The scan button opens a camera surface with a close button and a framing guide. Camera switching appears when multiple video inputs are available; the torch appears when the active camera reports support. A successful scan fills the field and closes the scanner. Enable `continuous()` to keep it open.

## Supported formats

| Enum case | Slug | Common use |
| --- | --- | --- |
| `BarcodeFormat::Qr` | `qr` | Tickets, URLs and QR codes |
| `BarcodeFormat::Ean13` | `ean_13` | Retail products |
| `BarcodeFormat::Ean8` | `ean_8` | Small retail labels |
| `BarcodeFormat::UpcA` | `upc_a` | UPC product labels |
| `BarcodeFormat::UpcE` | `upc_e` | Compressed UPC labels |
| `BarcodeFormat::Code128` | `code_128` | Warehouse and shipping labels |
| `BarcodeFormat::Code39` | `code_39` | Inventory labels |
| `BarcodeFormat::Itf` | `itf` | Cartons |
| `BarcodeFormat::Pdf417` | `pdf417` | Boarding passes and documents |
| `BarcodeFormat::DataMatrix` | `data_matrix` | Small parts and packaging |

All **10 formats listed above** are enabled by default; this does not mean every barcode symbology that exists. `formats()` and its alias `supportedFormats()` accept enum cases or the listed slugs. An empty list or an unknown format is a configuration error.

```php
use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;

BarcodeScannerField::make('ean')
    ->formats([
        BarcodeFormat::Ean13,
        BarcodeFormat::Ean8,
        BarcodeFormat::UpcA,
        BarcodeFormat::UpcE,
    ])
    ->validateChecksum()
    ->allowManualInput(false);
```

```php
BarcodeScannerField::make('ticket')
    ->formats([BarcodeFormat::Qr])
    ->scanButtonLabel('Scan ticket')
    ->modalHeading('Ticket scanner');
```

`modalHeading()` sets the scanner's accessible name; there is no visible header over the camera.

### All formats, a subset or dynamic selection

```php
// All ten supported formats.
BarcodeScannerField::make('any_supported_code');

// Only QR codes.
BarcodeScannerField::make('ticket')
    ->formats([BarcodeFormat::Qr]);

// A selected combination.
BarcodeScannerField::make('product')
    ->supportedFormats(['ean_13', 'ean_8', 'code_128']);

// Refresh when a controlling live field changes.
use Filament\Schemas\Components\Utilities\Get;

BarcodeScannerField::make('code')
    ->formats(fn (Get $get): array => $get('code_kind') === 'ticket'
        ? [BarcodeFormat::Qr]
        : [BarcodeFormat::Ean13, BarcodeFormat::Code128]);
```

The whitelist configures the decoder and is enforced again on the server. Prefer a smaller set when you know which labels your workflow uses.

### Your own company codes

Use a supported symbology to carry your own data, then validate its content. For example, encode `FLEX-A1B2C3D4` in a QR code:

```php
BarcodeScannerField::make('company_ticket')
    ->formats([BarcodeFormat::Qr])
    ->barcodeRule('regex:/^FLEX-[A-Z0-9]{8}$/D')
    ->confirmScan()
    ->required();
```

`barcodeRule()` validates on form submission. It does not install a camera decoder or add a name to `BarcodeFormat`. A new physical symbology outside the supported list needs a decoder implementation; passing an invented format name is rejected. Do not treat the camera's success sound as confirmation that your server-side business rule has passed.

## Store the detected format

```php
BarcodeScannerField::make('scanned_code')
    ->storeDetectedFormat();
```

Use a JSON column and an Eloquent `array` cast for structured values:

```php
protected function casts(): array
{
    return ['scanned_code' => 'array'];
}
```

A camera scan stores:

```json
{"value": "5901234123457", "format": "ean_13"}
```

Manual entry stores `format: null`. Changing the manually entered value clears its previous detected format. A plain string loaded into a structured field also receives `format: null`.

<Note>
The camera identifies a physical barcode format. Text entered by hand has no physical symbology to inspect. Server validation checks value patterns and any supplied format, but format metadata is not proof that a camera was used. Restrict formats and add application rules for values that require stronger checks.
</Note>

## Validation and business rules

Validation runs on the server before normal form state is saved. Invalid state types, unknown keys, invalid format metadata, nested values, null bytes and values over 4,096 UTF-8 bytes are rejected. A scalar field rejects structured payloads.

`validateChecksum()` verifies EAN-13, EAN-8, UPC-A and expanded UPC-E checksums. UPC-E needs its check digit: a six-digit body alone cannot pass checksum validation. When a numeric value matches an enabled EAN/UPC format, adding the broad QR format does not bypass checksum verification for manual entry.

```php
BarcodeScannerField::make('ean')
    ->formats([BarcodeFormat::Ean13])
    ->validateChecksum()
    ->required();
```

Use `barcodeRule()` for an additional Laravel rule string or a callback. The callback receives the extracted string in both storage modes.

```php
BarcodeScannerField::make('sku')
    ->formats([BarcodeFormat::Code39])
    ->barcodeRule('starts_with:SKU-');
```

```php
BarcodeScannerField::make('inventory_code')
    ->barcodeRule(function (
        string $attribute,
        mixed $value,
        \Closure $fail,
    ): void {
        if (! str_starts_with($value, 'WH-')) {
            $fail('Use a code from this warehouse.');
        }
    });
```

Manual typing remains editable while validation is handled on submission. Codes recognized by the camera are checked before replacing the current value.

## Continuous scanning

```php
BarcodeScannerField::make('last_scanned_item')
    ->continuous()
    ->scanDelay(750)
    ->scanInterval(150)
    ->beepOnScan();
```

Each accepted scan replaces the field's value. Continuous mode does not accumulate an array of scans. A code that stays in view is accepted once. The same code can be accepted again after it has left view for at least `scanDelay()`; a different code can be accepted immediately.

You can listen for the bubbling `barcode-scanned` event to maintain your own inventory workflow. Its detail contains `value`, `format`, `engine` and `statePath`.

```html
<div x-on:barcode-scanned="console.log($event.detail.value)">
    <!-- Render your form here. -->
</div>
```

## Preview before saving

```php
BarcodeScannerField::make('ticket')
    ->formats([BarcodeFormat::Qr])
    ->confirmScan();
```

A recognized code plays one sound, freezes a local camera frame and releases the camera. **Save code** commits the value; **Scan again** discards the pending result and restarts the camera. Closing the preview keeps the previous field value unchanged. The frame is a temporary in-memory preview: it is not uploaded or stored.

Confirmation is off by default. With `continuous()->confirmScan()`, saving starts the next scan; a code that remains visible is not repeatedly accepted. Automatic form submission happens only after confirmation.

## Camera, performance and privacy

The scanner uses the native detector when it supports **every** configured format. Otherwise it loads the bundled ZXing decoder when scanning starts. Rendering a field does not request camera permission or load ZXing. Native support is checked through `BarcodeDetector.getSupportedFormats()` rather than browser-name guessing.

| Browser capability | Selected engine | ZXing downloaded? |
| --- | --- | --- |
| Native supports every selected format | Native detector | No |
| Native exists but misses a selected format | ZXing | On scanner opening |
| No usable native detector | ZXing | On scanner opening |

For example, QR-only uses the native engine if QR is supported. The default ten-format set can require ZXing even when the browser exposes `BarcodeDetector`, because some of those formats may be unavailable natively. Previously evaluated JavaScript stays cached by the browser; the field does not claim to unload it.

Only one decoding operation runs at a time. The fallback uses a preview surface capped at 1,280 pixels on its longer edge. `scanInterval()` controls the minimum cadence between attempts; a slow attempt never starts another in parallel. Reader instances and the grayscale buffer are reused between attempts; decoding does not create a second camera stream. The ZXing fallback currently performs pixel decoding on the main thread, so recognition time depends on the device and image. Narrowing the format list and reducing `decodeFps()` can reduce its CPU cost.

```php
BarcodeScannerField::make('code')
    ->cameraFacing('environment')
    ->allowCameraSwitch()
    ->pauseWhenHidden()
    ->decodeFps(8);
```

The scanner releases the stream on closing, owner disposal or page departure. With `pauseWhenHidden()` enabled, it releases the camera while the document is hidden and restarts when visible. Permission denial and camera errors leave the scanner closable and offer a retry.

<Warning>
Camera access requires HTTPS or localhost, browser permission and an available camera. Embedded pages also need an appropriate camera Permissions Policy. Browser and hardware support determine available formats, torch and device switching. Mobile autoplay policies can prevent sound playback.
</Warning>

The confirmation sound is a declared, published component resource. It is prepared only after the scan-button gesture, and it does not delay accepting a code. Use the normal [asset publication step](/docs/installation) when deploying or updating the package; no separate sound-publishing command is required.

## Appearance

The input reuses FLEX Text Input's presentation, focus outline, sizes and rounding. Its scan action stays on the right.

```php
BarcodeScannerField::make('code')
    ->size('sm')
    ->variant('secondary')
    ->rounding('full')
    ->focusOutline()
    ->placeholder('Scan an inventory label');
```

Sizes: `sm`, `md`, `lg`. Variants: `primary`, `secondary`, `soft`, `flat`; `ghost` remains supported and uses the flat input presentation.

```php
BarcodeScannerField::make('code')
    ->scanIcon('hugeicons-qr-code')
    ->scanButtonLabel('Open scanner')
    ->beepOnScan(false);
```

## All scanner options

These options accept a `Closure` for dynamic configuration unless shown otherwise. They extend the normal Filament field options such as `label()`, `helperText()`, `required()`, `disabled()` and `readOnly()`.

| Method | Default | Purpose |
| --- | --- | --- |
| `formats(array\|Closure\|null)` | All formats | Restrict enabled barcode symbologies; `null` restores all formats |
| `supportedFormats(...)` | All formats | Alias of `formats()` |
| `confirmScan(bool\|Closure)` | `false` | Review a frozen frame and code before committing |
| `continuous(bool\|Closure)` | `false` | Keep scanning after an accepted code |
| `beepOnScan(bool\|Closure)` | `true` | Prepare and play the published confirmation sound |
| `autoSubmit(bool\|Closure)` | `false` | Dispatch `barcode-auto-submit` and request submission of the enclosing HTML form |
| `cameraFacing(string\|Closure)` | `environment` | Prefer `environment` or `user` camera |
| `preferredDeviceId(string\|Closure\|null)` | `null` | Request a specific device ID |
| `allowCameraSwitch(bool\|Closure)` | `true` | Offer switching when more than one camera is reported |
| `pauseWhenHidden(bool\|Closure)` | `true` | Release camera while the document is hidden |
| `allowManualInput(bool\|Closure)` | `true` | Allow typing and keyboard-style scanners |
| `storeDetectedFormat(bool\|Closure)` | `false` | Store `{value, format}` instead of a string; calling the method enables it |
| `validateChecksum(bool\|Closure)` | `false` | Enforce EAN/UPC check digits |
| `scanDelay(int\|Closure)` | `750` ms | Time outside view before rearming the same code, clamped to 0–60,000 ms |
| `scanInterval(int\|Closure)` | `120` ms | Decode cadence, clamped to 50–2,000 ms |
| `decodeFps(int\|Closure)` | Unset | Set cadence through frames per second; the same limits apply |
| `scanButtonLabel(string\|Closure)` | Translated label | Accessible scan-button label and title |
| `modalHeading(string\|Closure)` | Translated label | Accessible camera dialog name |
| `scanIcon(string\|BackedEnum\|Htmlable\|Closure\|null)` | QR icon | Override the scan-action icon |
| `barcodeRule(string\|Closure)` | Unset | Add a server-side business rule |
| `variant(string\|Closure)` | `primary` | Input surface variant |
| `size(string\|ControlSize\|Closure)` | `md` | Shared control size |
| `rounding(...)` | Shared default | Shared input rounding |
| `focusOutline(bool\|Closure)` | `true` | Shared focus outline |

`scanInterval()` and `decodeFps()` configure the same cadence; whichever you call last takes precedence. `autoSubmit()` does not choose a Filament modal action for you. Use its event to connect workflows whose submit control is outside the enclosing form. All normal form validation still applies.

## JSON field schemas

The field type is `barcode`.

```php
[
    'type' => 'barcode',
    'slug' => 'sku',
    'label' => 'Product code',
    'required' => true,
    'config' => [
        'formats' => ['ean_13', 'ean_8'],
        'validate_checksum' => true,
        'allow_manual_input' => false,
        'size' => 'md',
        'variant' => 'secondary',
    ],
]
```

The scanner configuration keys are `formats`, `confirm_scan`, `continuous`, `beep_on_scan`, `auto_submit`, `camera_facing`, `scan_delay`, `scan_interval`, `decode_fps`, `allow_camera_switch`, `preferred_device_id`, `store_detected_format`, `pause_when_hidden`, `allow_manual_input`, `validate_checksum`, `scan_button_label` and `modal_heading`. Keep PHP callbacks such as `barcodeRule()` in trusted application code.
