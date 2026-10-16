---
title: "CreditCardField"
description: Credit card input with a live card preview, shared FLEX text controls, server-side validation and configurable tokenization.
icon: "credit-card"
"canonical": "https://flex-fields.bjanczak.com/docs/creditcardfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/credit-card.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "CreditCardField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/credit-card-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/credit-card-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/credit-card.webp"
"twitter:image:alt": "CreditCardField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/creditcardfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/creditcardfield",
      "name": "CreditCardField",
      "description": "Credit card input with a live card preview, shared FLEX text controls, server-side validation and configurable tokenization.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/creditcardfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/creditcardfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/creditcardfield",
      "headline": "CreditCardField",
      "description": "Credit card input with a live card preview, shared FLEX text controls, server-side validation and configurable tokenization.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/creditcardfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/credit-card-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/credit-card-motion.mp4#video",
      "name": "CreditCardField — FLEX Fields demonstration",
      "description": "A recorded demonstration of CreditCardField in the FLEX Fields playground for Filament. Credit card input with a live card preview, shared FLEX text controls, server-side validation and configurable tokenization.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/credit-card.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT7.45S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/credit-card-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/creditcardfield#article"
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
  src="/art/previews/credit-card-motion.mp4"
  poster="/art/previews/credit-card.webp"
  width="1920"
  height="1080"
  aria-label="CreditCardField — FLEX Fields demonstration"
>
  <a href="/art/previews/credit-card-motion.mp4">Watch the CreditCardField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Payment input that combines four fields (number, name, expiry, CVV) into one cohesive component. Includes an interactive 3D-style card preview that flips when focusing the CVV field and validates numbers using the Luhn algorithm.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\CreditCardField` |
| **State type** | `array` — `{number, name, expiry, cvv}` |
| **Model cast** | `'payment_method' => 'array'` |
| **FieldType** | `credit-card` |
| **Playground** | `credit-card` slug in Flex Fields playground |
| **Default variant** | `midnight` |

---

### Basic usage

#### Standard payment form
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CreditCardField;

CreditCardField::make('card_details')
    ->label('Payment Method')
    ->required();
```

#### Custom labels and variant
```php
CreditCardField::make('card_details')
    ->variant('ocean')
    ->numberLabel('Numer karty')
    ->nameLabel('Imię i nazwisko')
    ->expiryLabel('Data ważności')
    ->cvvLabel('Kod CVV');
```

---

### State & validation

#### Input state and saved value

The four input values are strings: `number`, `name`, `expiry`, and `cvv`.
Numbers are grouped while typing and expiry uses `MM/YY`. Validation runs on the
server when the form is submitted; changing HTML or JavaScript cannot bypass it.

With the default `never_store_pan` policy, a nonempty card number requires a
registered tokenizer. A successful submission saves this shape:

```php
[
    'token' => 'token_returned_by_your_provider',
    'last4' => '4242',
    'name' => 'Test Card',
    'expiry' => '12/30',
];
```

CVV is excluded from the saved value in every configuration. A tokenizer returning
no token or throwing an exception produces a validation error. Its result is reused
between validation and saving within one field instance, avoiding a second provider
call for the same number.

This component collects new card details. Use a separate selection field for saved
payment tokens; a token is not a card number and must not be supplied as editable
card input. The default policy does not authorize saving raw card numbers.

#### Built-in Validation
`CreditCardField` performs several automatic checks:
- **Luhn Check**: Validates the card number checksum.
- **Expiry Date**: Ensures the card is not expired and the format is `MM/YY`.
- **CVV Format**: Validates 3 or 4 digit numeric codes.
- **Required Fields**: If `required()`, all four sub-fields must be filled, including when the whole value is empty.
- **Payload checks**: Unknown keys, nested values, control characters, oversized values and letters in numeric fields are rejected. Card numbers are limited to 13–19 digits and names to 200 characters.

Validation checks the submitted data before any display truncation. The display
formatter does not silently repair a malformed submission.

---

### Visual Customization

#### Variants
Choose from four built-in card themes:

| Variant | Colors |
|---------|--------|
| `midnight` | Deep dark / black (default) |
| `ocean` | Blue gradient |
| `sunset` | Orange / red gradient |
| `slate` | Neutral gray |

```php
CreditCardField::make('card')->variant('sunset');
```

#### Input Variants
Change the style of the text inputs below the card:
- `primary`: Standard bordered inputs.
- `secondary`: Subtle background-only inputs.
- `flat`: Transparent input surface.

```php
CreditCardField::make('card')->inputVariant('flat');
```

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `variant(string\|Closure $variant)` | Setup | `'midnight'` | Card theme: `midnight`, `ocean`, `sunset`, `slate` |
| `inputVariant(string\|Closure $var)` | Setup | `'primary'` | Input style: `primary`, `secondary`, `flat` |
| `flipOnCvvFocus(bool\|Closure $cond)`| Setup | `true` | Flip card to back when CVV is focused |
| `numberLabel(string\|Closure $label)`| Setup | (trans) | Card number field label |
| `nameLabel(string\|Closure $label)`  | Setup | (trans) | Cardholder name field label |
| `expiryLabel(string\|Closure $label)`| Setup | (trans) | Expiry date field label |
| `cvvLabel(string\|Closure $label)`   | Setup | (trans) | CVV field label |
| `mark(string\|Closure $mark)`        | Setup | translated | Text shown on the card preview |
| `size(string\|Closure $size)`        | Setup | `'md'` | `sm`, `md`, `lg` |
| `rounding(string\|Closure $round)`   | Setup | config | Border radius token |
| `focusOutline(bool\|Closure $cond)`  | Setup | `true` | Show the shared whole-input focus outline |
| `readOnly(bool\|Closure $cond)` | Setup | `false` | Keep values visible while preventing editing |
| `disabled(bool\|Closure $cond)` | Setup | `false` | Disable controls; native Filament dehydration rules apply |

---

### Real-world examples

#### Branded card collection
```php
use Filament\Schemas\Components\Section;

Section::make('Card details')
    ->schema([
        CreditCardField::make('payment_details')
            ->mark('Acme')
            ->variant('midnight')
            ->inputVariant('primary')
            ->required(),
    ]);
```

Register the tokenizer shown below before submitting nonempty card details under
the default storage policy.

---

### Playground

`/admin/flex-fields/credit-card`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [FlexTextInput](/docs/flextextinput) | Single masked input for card number only |
| [SelectField](/docs/selectfield) | Selecting from saved payment methods |
| [AddressAutocomplete](/docs/addressautocompletefield) | Billing address collection |

---

### PCI & Media Capture OS

CreditCardField integrates with [Media Ingress](/docs/media-capture-os) for its configurable card-storage policy.

| Env | Default | Purpose |
|-----|---------|---------|
| `FLEX_FIELDS_PCI_NEVER_STORE_PAN` | `true` | Dehydrate never writes raw PAN — only `token` + `last4` when tokenizer is registered |
| `FLEX_FIELDS_PCI_REQUIRE_TOKENIZATION` | `false` | When `true`, validation fails unless host registers `MediaCaptureOs::registerTokenizeCreditCardCallback()` |

Host wiring:

```php
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;

MediaCaptureOs::registerTokenizeCreditCardCallback(
    fn (string $pan): ?string => app(PaymentGateway::class)->tokenize($pan),
);
```

CVV is **never** included in the dehydrated value. Raw card input passes through
the application's Livewire form state. This field does not provide a hosted payment
iframe, encryption, payment authorization or PCI certification. Do not treat the
storage policy as a guarantee about application logs, sessions or provider handling.

### Shared controls and responsive behavior

All four controls use the same Blade input and shell as [Flex Text Input](/docs/flextextinput).
Their size, rounding, input variant, dark theme and focus outline come from that
shared component. Its stylesheet is declared as a shared dependency and loaded
once; the standalone text-input JavaScript is not required for these controls.

The card preview scales to its parent column, retains its four themes, supports
right-to-left form layout and respects reduced-motion preferences. Numeric inputs
keep left-to-right digit order. Formatting preserves the caret while editing a
number, and deleting beside a separator removes a digit rather than trapping the
cursor. The preview updates locally without timers, animation-frame loops or
geometry observers.

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-credit-card-field` | Root wrapper |
| `fff-credit-card__preview` | The 3D card container |
| `fff-credit-card__front` | Front side of card |
| `fff-credit-card__back` | Back side of card |
| `fff-credit-card__fields` | Grid of text inputs |
| `fff-credit-card-field--{variant}` | Theme variant class |
| `fff-credit-card-field--input-{variant}` | Input style variant class |
