---
title: "CoverCard"
description: Media card for hero banners, product tiles, and CTA blocks with background images and gradients.
icon: "panel-top"
"canonical": "https://flex-fields.bjanczak.com/docs/covercard"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/cover-card.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "CoverCard in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/cover-card-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/cover-card-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/cover-card.webp"
"twitter:image:alt": "CoverCard in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/covercard#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/covercard",
      "name": "CoverCard",
      "description": "Media card for hero banners, product tiles, and CTA blocks with background images and gradients.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/covercard#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/covercard#article",
      "url": "https://flex-fields.bjanczak.com/docs/covercard",
      "headline": "CoverCard",
      "description": "Media card for hero banners, product tiles, and CTA blocks with background images and gradients.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/covercard#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/cover-card-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/cover-card-motion.mp4#video",
      "name": "CoverCard — FLEX Fields demonstration",
      "description": "A recorded demonstration of CoverCard in the FLEX Fields playground for Filament. Media card for hero banners, product tiles, and CTA blocks with background images and gradients.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/cover-card.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT2.567S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/cover-card-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/covercard#article"
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
  src="/art/previews/cover-card-motion.mp4"
  poster="/art/previews/cover-card.webp"
  width="1920"
  height="1080"
  aria-label="CoverCard — FLEX Fields demonstration"
>
  <a href="/art/previews/cover-card-motion.mp4">Watch the CoverCard demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

**Media card** for hero banners, product tiles, and call-to-action blocks. Supports background images, gradients, or solid colors, with optional top/footer copy and a footer action button.

Cover Card renders on the server and requires no additional JavaScript controller.
Its stylesheet is prepared before the first paint when the card is initially
visible. Cards inside inactive tabs or closed modals keep demand-based loading.
Footer actions use Filament's action handling; loading is scoped to the clicked
action rather than every card on the page.

Footer copy and actions stay at the bottom of the card, including cards without
a top title. RTL is inherited from the page, or can be enabled on one card with
`extraAttributes(['dir' => 'rtl'])`. Text alignment and footer layout follow the
reading direction; the background image keeps its configured position.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Schemas\Components\CoverCard` |
| **State** | None — display/action component |
| **Playground** | `cover-card` slug in Flex Fields playground |
| **Extends** | `Filament\Schemas\Components\Component` |

---

### Basic usage

#### Portrait product card

```php
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\CoverCard;
use Bjanczak\FilamentFlexFields\Filament\Actions\Action;

CoverCard::make()
    ->backgroundImage('https://example.com/yacht.jpg')
    ->ratio('3:4')
    ->topTitle('Azimut 55 Fly')
    ->footerTitle('From €4,900 / week')
    ->footerAction(
        Action::make('book')->label('Book now')
    );
```

#### Full-width cinematic banner

```php
CoverCard::make()
    ->backgroundImage('https://example.com/harbor.jpg')
    ->ratio('21:9')
    ->tone('light')
    ->fullWidth()
    ->columnSpanFull()
    ->topTitle('Charter Season 2026')
    ->footerTitle('Early Booking Discount')
    ->footerDescription('Save 15% before March');
```

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `backgroundImage(string\|Closure\|null $url)` | Background | `null` | HTTPS or local image URL. Unsafe addresses are ignored; the configured fallback color or gradient remains available. HTTP requires an explicit application opt-in. |
| `backgroundGradient(string\|Closure\|null $css)` | Background | `null` | CSS gradient string (used if no image is set). |
| `backgroundColor(string\|Closure\|null $color)` | Background | `null` | Fallback / underlay color. |
| `backgroundPosition(string\|Closure $pos)` | Background | `'center'` | CSS `background-position`. |
| `ratio(string\|Closure\|null $ratio)` | Layout | `'3:4'` | Preferred aspect ratio (e.g., `16:9`, `1:1`, `auto`). Applied via a grid sizer so short ratios never clip footer actions when content needs more height. |
| `fullWidth(bool\|Closure $condition = true)` | Layout | `false` | Removes the default `20rem` max-width constraint. |
| `fullWidthOnMobile(bool\|Closure $condition = true)` | Layout | `false` | Below 640px, fills the available container width even if a desktop `contentMaxWidth()` is set. Height follows `ratio()` and can grow to keep content visible. |
| `contentMaxWidth(string\|Closure\|null $width)` | Layout | `null` | Custom `max-width` when not full width. |
| `topTitle(string\|Closure $text)` | Content | `null` | Header title text. |
| `topDescription(string\|Closure $text)` | Content | `null` | Header description text. |
| `footerTitle(string\|Closure $text)` | Content | `null` | Footer title text. |
| `footerDescription(string\|Closure $text)` | Content | `null` | Footer description text. |
| `footerAction(Action\|Closure $action)` | Content | `null` | Filament `Action` rendered as a pill CTA. |
| `tone(string\|Closure $tone)` | Appearance | `'dark'` | Text/scrim variant: `dark` or `light`. |
| `radius(string\|Closure $radius)` | Appearance | `'xl'` | Corner radius: `md`, `lg`, `xl`, `2xl`. |
| `contentOverlays(bool\|Closure $on)` | Appearance | `false` | Enables separate top/bottom gradient overlays. |
| `topOverlayGradient(string\|Closure\|null $css)` | Appearance | `null` | Custom CSS gradient for top overlay. |
| `footerOverlayGradient(string\|Closure\|null $css)` | Appearance | `null` | Custom CSS gradient for bottom overlay. |

Ratios accept positive finite numbers, `width:height`, `width / height`, `auto`
or `null`. Zero, negative or malformed dimensions throw an exception. Appearance
options accept CSS values, including variables and gradient functions; additional
declarations, markup and resource-loading functions are rejected. Supply image
URLs through `backgroundImage()`.

If multiple cards use the same action name, give each card a distinct `key()`:

```php
CoverCard::make()
    ->key('featured-product')
    ->footerAction(Action::make('view')->label('View details'));
```

Cover Card does not store a value in the database. Use your footer action's form,
authorization and validation to control any changes to application data.

#### Full width on mobile, compact on desktop

```php
CoverCard::make()
    ->backgroundImage('/images/product-cover.jpg')
    ->contentMaxWidth('20rem')
    ->fullWidthOnMobile()
    ->ratio('3:4')
    ->topTitle('Featured product');
```

This fills the width provided by the surrounding grid or section; it does not
remove the page's padding. No JavaScript resize handler is needed.

#### Public helper methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getAspectRatioStyle()` | `?string` | Resolved CSS `aspect-ratio` value. |
| `getBackgroundStyles()` | `array` | Inline style fragments for the background layer. |
| `hasTopContent()` | `bool` | Whether top copy blocks are filled. |
| `hasFooterContent()` | `bool` | Whether footer copy or action exists. |

---

### Real-world examples

#### Gradient feature strip

```php
CoverCard::make()
    ->backgroundGradient('linear-gradient(90deg, #4c1d95 0%, #be185d 100%)')
    ->ratio('3:1')
    ->tone('light')
    ->fullWidth()
    ->footerTitle('Premium Features')
    ->footerDescription('Unlock advanced analytics and reporting');
```

#### Vertical card in a grid

```php
CoverCard::make()
    ->backgroundImage($product->image_url)
    ->ratio('9:16')
    ->contentMaxWidth('16rem')
    ->footerTitle($product->name)
    ->footerDescription($product->price_formatted)
    ->footerAction(Action::make('view')->label('View details'));
```

---

### Playground

`/admin/flex-fields/cover-card`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [ItemCard](/docs/itemcard) | For standard list rows with icons and descriptions. |
| [ItemCardGroup](/docs/itemcardgroup) | To group multiple item cards in a shared container. |
| [ProgressCircle](/docs/progresscircle) | When the card should focus on a progress metric. |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-cover-card` | Root card container |
| `fff-cover-card--tone-{dark\|light}` | Text contrast variant |
| `fff-cover-card--radius-{md\|lg\|xl\|2xl}` | Corner radius variant |
| `is-full-width` | Full width modifier |
| `fff-cover-card__background` | Background image/color layer |
| `fff-cover-card__scrim` | Global gradient overlay |
| `fff-cover-card__overlay--top` | Top-specific gradient overlay |
| `fff-cover-card__overlay--bottom` | Bottom-specific gradient overlay |
| `fff-cover-card__content` | Copy and action container |
| `fff-cover-card__footer-action` | Action button wrapper |
