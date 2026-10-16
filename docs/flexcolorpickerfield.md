---
title: "FlexColorPickerField"
description: "Configure a Filament color picker with HEX, RGB and HSL output, opacity, custom palettes, compact swatches and inline controls."
icon: "palette"
"canonical": "https://flex-fields.bjanczak.com/docs/flexcolorpickerfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/flex-color-picker.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "FlexColorPickerField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/flex-color-picker-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/flex-color-picker-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/flex-color-picker.webp"
"twitter:image:alt": "FlexColorPickerField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/flexcolorpickerfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/flexcolorpickerfield",
      "name": "FlexColorPickerField",
      "description": "Configure a Filament color picker with HEX, RGB and HSL output, opacity, custom palettes, compact swatches and inline controls.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexcolorpickerfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/flexcolorpickerfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/flexcolorpickerfield",
      "headline": "FlexColorPickerField",
      "description": "Configure a Filament color picker with HEX, RGB and HSL output, opacity, custom palettes, compact swatches and inline controls.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexcolorpickerfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/flex-color-picker-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/flex-color-picker-motion.mp4#video",
      "name": "FlexColorPickerField — FLEX Fields demonstration",
      "description": "A recorded demonstration of FlexColorPickerField in the FLEX Fields playground for Filament. Configure a Filament color picker with HEX, RGB and HSL output, opacity, custom palettes, compact swatches and inline controls.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/flex-color-picker.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT7.467S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/flex-color-picker-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexcolorpickerfield#article"
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
  src="/art/previews/flex-color-picker-motion.mp4"
  poster="/art/previews/flex-color-picker.webp"
  width="1920"
  height="1080"
  aria-label="FlexColorPickerField — FLEX Fields demonstration"
>
  <a href="/art/previews/flex-color-picker-motion.mp4">Watch the FlexColorPickerField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Choose colors with a saturation and brightness area, preset palettes or channel sliders. Use a full-width field, a compact color circle or an inline panel, and choose how the value is saved.

<CardGroup cols={2}>
  <Card title="Choose your controls" icon="sliders-horizontal" href="#choose-a-panel-layout">
    Four panel layouts, optional opacity and your own palette.
  </Card>
  <Card title="Choose your output" icon="code" href="#formats-and-saved-values">
    Save HEX, RGB, RGBA, HSL or HSLA strings.
  </Card>
</CardGroup>

## Start with one field

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexColorPickerField;

FlexColorPickerField::make('brand_color')
    ->label('Brand color')
    ->default('#6366F1')
    ->required();
```

The default is an **advanced panel with HEX output**. Opacity is off. Click anywhere on the field to open the panel. The format selector offers HEX, RGB and HSL; the screen-color picker appears when the browser supports it.

## Choose a panel layout

<Tabs>
  <Tab title="Advanced" icon="square">
    A saturation and brightness area with a hue slider. Add opacity, channel inputs or preset swatches when needed.

    ```php
    FlexColorPickerField::make('accent')
        ->layout('advanced')
        ->showSwatches()
        ->alpha();
    ```
  </Tab>
  <Tab title="Grid" icon="grid-3x3">
    A grid of preset colors. The default generated grid has 17 columns and 11 rows. Supply your own colors to replace it.

    ```php
    FlexColorPickerField::make('category_color')
        ->layout('grid')
        ->gridColumns(8)
        ->gridRows(6)
        ->closeOnSelect();
    ```
  </Tab>
  <Tab title="Swatches" icon="circle">
    Circular swatches, with nine default colors. Custom colors wrap onto additional rows.

    ```php
    FlexColorPickerField::make('status_color')
        ->layout('swatches')
        ->gridColors(['#22C55E', '#F59E0B', '#EF4444'])
        ->showFields(false)
        ->closeOnSelect();
    ```
  </Tab>
  <Tab title="Sliders" icon="sliders-horizontal">
    Three RGB or HSL channel sliders without a color area or numeric input row. Switch between RGB and HSL, or keep a single representation.

    ```php
    FlexColorPickerField::make('surface_color')
        ->layout('sliders')
        ->hsl()
        ->allowedFormats(['hsl', 'rgb'])
        ->alpha()
        ->eyedropper(false);
    ```
  </Tab>
</Tabs>

`showSwatches()` adds circular presets to the advanced or sliders panel. On a grid panel, it changes the palette to circular swatches. Use `gridColors()` to choose the colors shown in any palette.

## Choose the field appearance

### Full field, icon or color circle

<CodeGroup>
```php Full field
FlexColorPickerField::make('brand_color')
    ->trigger('field')
    ->showValue();
```

```php Icon button
FlexColorPickerField::make('zone_color')
    ->iconOnly()
    ->showFields(false)
    ->eyedropper(false);
```

```php Color circle
FlexColorPickerField::make('label_color')
    ->swatchOnly()
    ->layout('swatches')
    ->showFields(false)
    ->closeOnSelect();
```
</CodeGroup>

`iconOnly()` shows a palette icon colored with the current value. `swatchOnly()` shows a color circle without an outer input frame. Both omit the value text. Alternatively, use `trigger('icon')` or `trigger('swatch')` directly.

### Sizes, variants and rounding

The main field shares FLEX Text Input's appearance: **three sizes**, four variants, rounding and an optional focus outline. These settings affect the field; `panelWidth()` and `panelRadius()` configure the panel separately.

```php
FlexColorPickerField::make('accent')
    ->size('sm')
    ->variant('secondary')
    ->rounding('full')
    ->focusOutline();
```

| Setting | Choices | Default |
| --- | --- | --- |
| `size()` | `sm`, `md`, `lg` | `md` |
| `variant()` | `primary`, `secondary`, `soft`, `flat` | `primary` |
| `rounding()` | `native`, `none`, `sm`, `md`, `lg`, `xl`, `full` | Shared `field_rounding` setting, normally `md` |
| `focusOutline()` | `true`, `false` | `false` |

See [Configuration](/docs/configuration) for shared appearance settings. The picker supports light and dark themes. Its format selector uses the FLEX Select control, and its value inputs use shared FLEX input controls.

### Always-visible panel

`inline()` displays the controls directly in the form and removes the opening trigger. `closeOnSelect()` does not hide an inline panel.

```php
FlexColorPickerField::make('theme_color')
    ->inline()
    ->showSwatches()
    ->alpha()
    ->panelWidth(320)
    ->panelRadius(24);
```

## Formats and saved values

**The saved format and the editing format are separate.** `format()` determines how new color selections are written to the field. Changing the format selector changes the editing controls without changing this setting.

<CodeGroup>
```php HEX
FlexColorPickerField::make('accent')->hex();
```

```php RGB
FlexColorPickerField::make('accent')->rgb();
```

```php RGBA
FlexColorPickerField::make('accent')->rgba()->alpha();
```

```php HSL
FlexColorPickerField::make('accent')->hsl();
```

```php HSLA
FlexColorPickerField::make('accent')->hsla();
```
</CodeGroup>

| Output setting | Example after selection | With opacity below 100% |
| --- | --- | --- |
| `hex()` | `#6366F1` | Eight-digit HEX, such as `#6366F180` |
| `rgb()` | `rgb(99, 102, 241)` | RGBA |
| `rgba()->alpha()` | `rgba(99, 102, 241, 1)` | RGBA |
| `hsl()` | `hsl(239, 84%, 67%)` | HSLA |
| `hsla()` | `hsla(239, 84%, 67%, 1)` | HSLA |

`format('hex')`, `format('rgb')`, `format('rgba')`, `format('hsl')` and `format('hsla')` are equivalent configuration choices. **`hsla()` enables opacity automatically; `rgba()` does not.** Use `alpha()` with RGBA output.

### Limit the editing formats

```php
FlexColorPickerField::make('accent')
    ->hex()
    ->allowedFormats(['hex', 'rgb']);

FlexColorPickerField::make('hex_color')
    ->allowedFormats(['hex']);
```

A single allowed format hides the selector. If no output format was explicitly set, the first allowed format becomes the output format. An explicitly configured output format must be included in `allowedFormats()`.

With opacity disabled, `rgba` and `hsla` configuration choices become `rgb` and `hsl`. Duplicate choices are removed.

<Note>
  Existing valid color strings are not converted simply by opening a form. Conversion to the configured output happens when the user changes the color. `allowedFormats()` controls editing choices; it does not reject an existing color solely because it uses another supported representation.
</Note>

### Opacity

Opacity is **off by default**. Enable it to show the opacity slider and preserve transparency. `opacity()` is an alias for `alpha()`.

```php
FlexColorPickerField::make('overlay_color')
    ->rgba()
    ->alpha()
    ->default('rgba(99, 102, 241, 0.5)');

FlexColorPickerField::make('solid_color')
    ->hex()
    ->alpha(false);
```

With opacity off, a transparent color entered interactively becomes opaque. A transparent value submitted directly to the server fails the field's validation. Use an opaque default when opacity is off.

## Custom palettes

Show brand colors alongside the full picker:

```php
FlexColorPickerField::make('brand_color')
    ->showSwatches()
    ->gridColors([
        '#6366F1',
        '#8B5CF6',
        '#0F172A',
        '#FFFFFF',
    ]);
```

Or offer only a compact preset interface:

```php
FlexColorPickerField::make('badge_color')
    ->swatchOnly()
    ->layout('swatches')
    ->gridColors(['#22C55E', '#F59E0B', '#EF4444'])
    ->showFields(false)
    ->closeOnSelect();
```

`gridColors()` accepts up to **512 valid color strings** and removes duplicate strings. `null` or an empty list restores the default palette. Generated grids allow 1–32 columns and 1–32 rows, with at most 512 cells in total. Grid dimensions do not generate additional colors when you supply a custom palette.

<Note>
  A palette configures the choices shown to the user; it is not a server-side allowlist. If your application requires one of a fixed set of saved values, add an explicit validation rule.
</Note>

```php
use Illuminate\Validation\Rule;

FlexColorPickerField::make('status_color')
    ->hex()
    ->layout('swatches')
    ->gridColors(['#22C55E', '#F59E0B', '#EF4444'])
    ->showFields(false)
    ->required()
    ->rule(Rule::in(['#22C55E', '#F59E0B', '#EF4444']));
```

## Show only the controls you need

<AccordionGroup>
  <Accordion title="Hide the format selector, keep the screen-color picker">
    ```php
    FlexColorPickerField::make('accent')
        ->hex()
        ->showFormatSelector(false)
        ->eyedropper();
    ```

    The screen-color picker remains available in advanced and sliders layouts even when the selector or value fields are hidden. With a visible selector, its button sits beside that selector. Otherwise, it sits beside the sliders. It never needs a separate full-width row.
  </Accordion>
  <Accordion title="Use one color-string input instead of separate channels">
    ```php
    FlexColorPickerField::make('accent')
        ->rgb()
        ->channelFields(false)
        ->alpha();
    ```

    This displays one color-string input. When opacity is enabled, it also displays a separate opacity number input. With `channelFields(true)`, RGB and HSL have individual channel inputs; HEX has one string input.
  </Accordion>
  <Accordion title="Hide numeric fields">
    ```php
    FlexColorPickerField::make('accent')
        ->showFields(false)
        ->showSwatches();
    ```

    This hides the numeric/string input section and its format selector. In the sliders layout, the format selector is independent of `showFields()`; use `showFormatSelector(false)` to hide it there.
  </Accordion>
  <Accordion title="Hide slider labels or the main value text">
    ```php
    FlexColorPickerField::make('accent')
        ->showChannelLabels(false)
        ->showValue(false);
    ```

    `showChannelLabels(false)` hides the headings and numeric readouts above sliders. `showValue(false)` hides the main field's color text. Neither removes the controls' accessible labels.
  </Accordion>
  <Accordion title="Turn off the screen-color picker">
    ```php
    FlexColorPickerField::make('accent')
        ->eyedropper(false);
    ```

    The screen-color picker is available only in advanced and sliders layouts, when enabled and supported by the browser. Unsupported browsers hide the button and retain the other controls.
  </Accordion>
</AccordionGroup>

## Panel position and motion

```php
FlexColorPickerField::make('accent')
    ->placement('bottom-start')
    ->panelWidth(300)
    ->panelRadius(24)
    ->animationDuration(150);
```

The default dropdown is aligned to the start edge of the field with a small gap. It follows the field while scrolling or resizing, stays inside the viewport, and can flip above the field when there is not enough room below.

| Method | Default | Supported values |
| --- | --- | --- |
| `placement()` | `bottom-start` | `bottom-start`, `bottom-end`, `top-start`, `top-end` |
| `panelWidth()` | `248` | 220–420 pixels; dropdown width is limited by the viewport |
| `panelRadius()` | `20` | 0–32 pixels, independent of field rounding |
| `animationDuration()` | `150` | 0–500 milliseconds; exit takes two thirds of the entry duration |

Use `animationDuration(0)` to disable transitions. The picker also respects reduced-motion preferences.

## Validation and database storage

The field stores a **color string**, or an empty value when optional. Use a nullable string column for optional colors; a length of 128 accommodates every accepted value. No JSON column or color-specific model cast is required.

The built-in server rule checks color syntax and channel ranges. Supported inputs are:

| Representation | Accepted form |
| --- | --- |
| HEX | Three, six or eight hexadecimal digits after `#` |
| RGB / RGBA | Comma-separated integer channels from 0 to 255; alpha from 0 to 1 |
| HSL / HSLA | Hue from 0 to 360; saturation and lightness from 0% to 100%; alpha from 0 to 1 |

Named colors such as `red`, four-digit HEX, CSS variables, URLs, CSS expressions and strings longer than 128 characters are not accepted. Modern space-separated CSS color notation is not supported. Empty values are allowed unless you use `required()`.

```php
FlexColorPickerField::make('brand_color')
    ->label('Brand color')
    ->default('#6366F1')
    ->required()
    ->helperText('Choose the color used on your public profile.');
```

Invalid text in the picker shows an error and keeps the previous valid form value. Add your application's rules with `rule()` or `rules()`; use [Data & validation](/docs/data-and-validation) for form saving guidance.

### Read-only and disabled fields

```php
FlexColorPickerField::make('brand_color')->readOnly();

FlexColorPickerField::make('brand_color')->disabled();
```

Both prevent opening a dropdown picker or changing inline controls. Read-only keeps the field's normal saving behavior; disabled fields follow Filament's disabled-field saving behavior. Decide separately whether the value should be included when your form is saved.

### React to a changed color

```php
use Filament\Schemas\Components\Utilities\Set;

FlexColorPickerField::make('brand_color')
    ->live()
    ->afterStateUpdated(function (Set $set, ?string $state): void {
        $set('preview_color', $state);
    });
```

Dragging the color area updates its preview as you move and commits the field value on release. Sliders commit on their change event. Use the normal Filament state callbacks for application behavior.

## Keyboard controls

| Control | Keyboard behavior |
| --- | --- |
| Field trigger | Enter or Space opens the panel |
| Color area | Arrow keys adjust saturation or brightness; Shift uses a larger step |
| Color area | Home sets saturation to 0%; End sets it to 100% |
| Sliders | Standard range-input keyboard controls |
| Value inputs | Enter or leaving the input applies a valid value |
| Dropdown panel | Escape closes it and returns focus to the trigger |

You can also drag the color area and sliders with a pointer or touch. Opening the nested format selector keeps the color picker available.

## Configuration reference

These are the Color Picker's configuration methods. Setters with a value parameter also accept a closure, so each field can resolve its settings from the current form or record.

| Method | Default | Purpose |
| --- | --- | --- |
| `layout()` | `advanced` | Choose `advanced`, `grid`, `swatches` or `sliders` |
| `trigger()` | `field` | Choose `field`, `icon` or `swatch` |
| `iconOnly()` / `swatchOnly()` | — | Select a compact trigger and hide its value text |
| `inline()` | `false` | Keep the panel visible in the form |
| `format()` | `hex` | Choose the output format |
| `hex()` / `rgb()` / `rgba()` / `hsl()` / `hsla()` | — | Output-format shortcuts; `hsla()` also enables opacity |
| `allowedFormats()` | `hex`, `rgb`, `hsl` | Editing choices; alpha adds `rgba` and `hsla` by default; 1–5 entries |
| `alpha()` / `opacity()` | `false` | Enable transparency and opacity controls |
| `eyedropper()` | `true` | Offer screen-color selection where supported |
| `showValue()` | `true` | Show the color string in a full field |
| `showFields()` | `true` | Show panel value inputs; sliders do not have these inputs |
| `channelFields()` | `true` | Separate RGB/HSL inputs instead of one color-string input |
| `showFormatSelector()` | `true` | Show editing-format choices when more than one is allowed |
| `showChannelLabels()` | `true` | Show slider headings and numeric readouts |
| `showSwatches()` | `false` | Show circular palette presets |
| `closeOnSelect()` | `false` | Close a dropdown after selecting a palette color |
| `gridColors()` | Default palette | Supply up to 512 colors, or reset with `null` / `[]` |
| `gridColumns()` / `gridRows()` | `17` / `11` | Generated grid dimensions, 1–32 each, at most 512 cells |
| `panelWidth()` / `panelRadius()` | `248` / `20` | Panel dimensions in pixels |
| `placement()` | `bottom-start` | Preferred dropdown alignment |
| `animationDuration()` | `150` | Entry duration in milliseconds |
| `variant()` / `size()` | `primary` / `md` | Shared FLEX field appearance |
| `rounding()` / `focusOutline()` | Shared rounding / `false` | Field rounding and focus outline |

Invalid layout names, unsupported formats and out-of-range panel or grid settings are rejected when the field configuration is resolved.
