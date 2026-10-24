---
title: "FlexSlider"
description: "Pill and circular sliders for Filament with elastic motion, rolling numbers, magnetic marks and validated ranges."
icon: "sliders-horizontal"
"canonical": "https://flex-fields.bjanczak.com/docs/flexslider"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/flex-slider.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "FlexSlider in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/flex-slider-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/flex-slider-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/flex-slider.webp"
"twitter:image:alt": "FlexSlider in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/flexslider#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/flexslider",
      "name": "FlexSlider",
      "description": "Pill and circular sliders for Filament with elastic motion, rolling numbers, magnetic marks and validated ranges.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexslider#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/flexslider#article",
      "url": "https://flex-fields.bjanczak.com/docs/flexslider",
      "headline": "FlexSlider",
      "description": "Pill and circular sliders for Filament with elastic motion, rolling numbers, magnetic marks and validated ranges.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexslider#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/flex-slider-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/flex-slider-motion.mp4#video",
      "name": "FlexSlider — FLEX Fields demonstration",
      "description": "A recorded demonstration of FlexSlider in the FLEX Fields playground for Filament. Pill and circular sliders for Filament with elastic motion, rolling numbers, magnetic marks and validated ranges.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/flex-slider.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT6.933S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/flex-slider-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexslider#article"
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
  src="/art/previews/flex-slider-motion.mp4"
  poster="/art/previews/flex-slider.webp"
  width="1920"
  height="1080"
  aria-label="FlexSlider — FLEX Fields demonstration"
>
  <a href="/art/previews/flex-slider-motion.mp4">Watch the FlexSlider demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Use a slider for volume, percentages, prices or a range of values. Choose an inside pill handle or a circular handle outside the track. Both styles support the same configuration, icons and optional elastic motion.

<CardGroup cols={2}>
  <Card title="Try both styles" icon="play" href="https://demo.bjanczak.com/admin/flex-fields/flex-slider">Compare sizes, ranges, tooltips, marks and elastic motion.</Card>
  <Card title="Need precise increments?" icon="calculator" href="/docs/numberstepper">NumberStepper provides buttons and a directly editable number.</Card>
</CardGroup>

## Your first slider

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;

FlexSlider::make('volume')
    ->label('Volume')
    ->range(0, 100)
    ->step(1)
    ->default(64)
    ->fillTrack()
    ->showValue()
    ->suffix('%');
```

The field stores a number. Its suffix and prefix affect presentation, not the saved value.

## Two styles, one configuration

<Tabs>
<Tab title="Pill">

```php
FlexSlider::make('volume')
    ->range(0, 100)
    ->step(1)
    ->default(64)
    ->trackLabel('Volume')
    ->suffix('%')
    ->fillTrack()
    ->showValue()
    ->valuePlacement('header')
    ->thumbStyle('pill')
    ->elastic()
    ->tooltips()
    ->startIcon('hugeicons-volume-low')
    ->endIcon('hugeicons-volume-high')
    ->marks([0, ['value' => 50, 'label' => '50%'], 100]);
```

</Tab>
<Tab title="Circle">

```php
FlexSlider::make('playback_speed')
    ->range(0.5, 2)
    ->step(0.1)
    ->default(1)
    ->trackLabel('Playback speed')
    ->suffix('×')
    ->fillTrack()
    ->showValue()
    ->valuePlacement('header')
    ->thumbStyle('circle')
    ->elastic()
    ->tooltips()
    ->marks([
        ['value' => 0.5, 'label' => '0.5×'],
        ['value' => 1, 'label' => '1×'],
        ['value' => 1.5, 'label' => '1.5×'],
        ['value' => 2, 'label' => '2×'],
    ]);
```

</Tab>
</Tabs>

`thumbStyle()` changes the handle's appearance. `elastic()` independently enables stretching, a spring return and a moving tooltip. Either style can use either motion setting. The pill keeps its height during dragging; only the circle style thickens the rail and enlarges the pressed handle. The circular rail spans the full control width, while handle centers stay half a diameter inside its edges at the minimum and maximum. Numeric readouts use the same rolling-number animation as NumberStepper. The browser's reduced-motion preference suppresses movement. Touch dragging follows the same bounds and step rules as mouse dragging; vertical page scrolling remains available beside a horizontal track.

## Tooltips and readouts

`tooltips()` shows the active handle's value during dragging and keyboard changes. Hover alone does not open a tooltip. After a drag, the tooltip remains briefly visible; a keyboard tooltip closes when the handle loses focus. `tooltips(false)` disables it.

```php
FlexSlider::make('opacity')
    ->range(0, 100)
    ->step(1)
    ->default(75)
    ->suffix('%')
    ->fillTrack()
    ->tooltips();
```

`showValue()` adds a readout. Use `valuePlacement('header')` to keep it beside the track label, including sliders with tooltips or pips. For a single handle without tooltips or pips, the default `footer` placement shows a row below the track; `valuePosition('start'|'center'|'end')` aligns that footer's value. For multiple handles, `footer` places a value beneath each handle. When an enabled tooltip is visible, the header readout fades out and returns afterwards.

<Note>
A single-handle footer is omitted when tooltips or pips are enabled. Choose `valuePlacement('header')` if you want a permanent readout in that combination.
</Note>

For per-handle tooltips, use `tooltips([true, false])`. For a custom textual formatter, use `RawJs`:

```php
use Filament\Support\RawJs;

FlexSlider::make('price')
    ->range(0, 1000)
    ->step(10)
    ->fillTrack()
    ->tooltips(RawJs::make('`$${$value.toFixed(2)}`'));
```

Custom JavaScript tooltip text uses your formatter directly. For rolling digits, use normal `tooltips()` with `prefix()`, `suffix()`, `locale()` and `decimalPlaces()`.

## Optional circular icon button

`startIcon()` and `endIcon()` are decorative: they never change the value.
Use the separate `iconButton()` API when you want a clickable circular button.
It switches to a chosen value and restores the previous value on the next click.
The same API works for volume, brightness, a speed preset or another numeric setting.

### Volume: mute and restore

```php
FlexSlider::make('volume')
    ->range(0, 100)
    ->step(1)
    ->default(64)
    ->suffix('%')
    ->showValue()
    ->valuePlacement('header')
    ->fillTrack()
    ->thumbStyle('circle')
    ->elastic()
    ->tooltips()
    ->iconButton('hugeicons-volume-high')
    ->iconButtonActiveIcon('hugeicons-volume-off')
    ->iconButtonValue(0)
    ->iconButtonRestoreValue(50)
    ->iconButtonLabel('Mute')
    ->iconButtonActiveLabel('Restore volume')
    ->endIcon('hugeicons-volume-high');
```

The button changes the field value; connect that value to your application's audio
controls if needed. The button is available with either thumb style and is absent
by default. Its normal and active icons are configured separately from decorative
icons. Its accessible label and pressed state follow the current value immediately.

### A custom preset on the right

```php
FlexSlider::make('brightness')
    ->range(0, 100)
    ->step(1)
    ->default(30)
    ->fillTrack()
    ->startIcon('hugeicons-sun-03')
    ->iconButton('hugeicons-sun-03')
    ->iconButtonPosition('end')
    ->iconButtonValue(100)
    ->iconButtonLabel('Maximum brightness')
    ->iconButtonActiveLabel('Restore brightness');
```

`iconButtonPosition()` accepts `start` or `end`. The button replaces a decorative
icon on its own side; the other side remains independent. Omitting
`iconButtonValue()` targets the range minimum. `iconButtonRestoreValue()` supplies
a fallback when the initial value equals the target and no previous value exists.
The default fallback is the middle of the selectable range, or an endpoint when
the middle is the target. Target and restore values follow the configured step
and must be selectable and different. Each field remembers its own previous value,
including subsequent keyboard and drag changes. Disabled fields cannot invoke
the button; it also stays disabled during a drag. Icon buttons support one handle;
range sliders continue to support decorative icons. Normal server validation applies.

## Magnetic marks and optional vibration

Marks can be numbers, labelled values or both. Endpoint labels are allowed; dots appear inside the track. Selecting a label changes the value. During dragging, nearby marks attract the handle within `detentDistance()` pixels.

```php
FlexSlider::make('zoom')
    ->range(1, 4)
    ->step(0.1)
    ->default(1)
    ->thumbStyle('circle')
    ->elastic()
    ->tooltips()
    ->marks([
        ['value' => 1, 'label' => '1×'],
        ['value' => 2, 'label' => '2×'],
        ['value' => 4, 'label' => '4×'],
    ])
    ->detentDistance(7)
    ->stretchLimit(26)
    ->haptics();
```

Vibration is opt-in, applies to touch interactions and requires browser/device support. Unsupported devices continue normally. Marks must be inside the configured range; use values compatible with your step.

## A range with two handles

```php
FlexSlider::make('budget')
    ->range(0, 1000)
    ->step(10)
    ->rangeHandles()
    ->default([200, 800])
    ->thumbStyle('circle')
    ->minDifference(100)
    ->maxDifference(700)
    ->fillTrack([false, true, false])
    ->prefix('$')
    ->showValue()
    ->tooltips();
```

The stored value is `[200, 800]`. Use a JSON column with an `array` model cast for this shape. `rangeHandles()` requires exactly two values. Multiple handles can also be defined by an array state; the maximum supported count is 128.

`minDifference()` prevents handles getting too close; `maxDifference()` limits their distance. Values are ordered unless you explicitly select the unconstrained behavior. `rangePadding(10)` reserves both ends; `rangePadding([10, 20])` reserves different amounts.

```php
use Filament\Forms\Components\Slider\Enums\Behavior;

FlexSlider::make('window')
    ->range(0, 100)
    ->rangeHandles()
    ->default([20, 60])
    ->fillTrack([false, true, false])
    ->behavior([Behavior::Tap, Behavior::Drag, Behavior::Fixed]);
```

`Tap` enables track selection. `Drag` enables dragging the filled range. `Fixed` moves handles together while preserving the interval. `Unconstrained` permits crossed handles. `SmoothSteps` is the smooth-step behavior accepted by the underlying constraint solver; the submitted value still follows the configured step. Use `Fixed` with exactly two handles and `Drag`. Do not combine `Unconstrained` with `minDifference()` or `maxDifference()`.

```php
// Select only by moving a handle; clicking the track does nothing.
FlexSlider::make('level')
    ->range(0, 100)
    ->default(50)
    ->behavior(null);

// Independent handles that may cross.
FlexSlider::make('markers')
    ->range(0, 100)
    ->default([25, 75])
    ->behavior([Behavior::Tap, Behavior::Unconstrained])
    ->tooltips();
```

Use `rangePadding()` in numeric units, not percentages. Handle-distance limits and padding use integers. Clear them with `minDifference(null)`, `maxDifference(null)` or `rangePadding(null)`.

## Formatting, size and direction

```php
FlexSlider::make('discount')
    ->range(0, 100)
    ->step(0.5)
    ->decimalPlaces(1)
    ->locale('pl_PL')
    ->suffix('%')
    ->size('lg')
    ->color('primary')
    ->showValue()
    ->valuePosition('center');
```

Choose `sm`, `md` or `lg`. `variant('secondary')` provides a neutral track accent; `color()` selects a Filament color and `fillColor('#6366f1')` supplies your own accent. `rtl()` reverses horizontal direction. `vertical()` switches orientation; configure enough height for a vertical field in your layout.

## Non-linear scales and pips

```php
FlexSlider::make('capacity')
    ->range(0, 1000)
    ->nonLinearPoints(['50%' => 100])
    ->default(100)
    ->fillTrack()
    ->showValue();
```

The first half of this track represents 0–100; the second represents 100–1000. Intermediate positions and values must strictly increase. You can provide `[value, step]` at a point for a segment's step.

```php
use Filament\Forms\Components\Slider\Enums\PipsMode;

FlexSlider::make('rating')
    ->range(0, 100)
    ->step(10)
    ->default(50)
    ->fillTrack()
    ->pips(PipsMode::Steps, density: 20)
    ->showStepDots();
```

Pips label the scale below the track. `pipsMode()`, `pipsDensity()`, `pipsValues()`, `steppedPips()`, `pipsFormatter()` and `pipsFilter()` configure their mode, density, positions, step alignment and custom JavaScript presentation. Step dots are off by default. Up to ten intervals show every step; longer scales show up to eleven evenly distributed dots. They are omitted for vertical controls, continuous scales and scales with more than 100 intervals.

## Keyboard and validation

Arrow keys move one step. Shift + Arrow and Page Up/Down use `largeStep()`; by default this is at least one step and approximately one tenth of the range. Home/End select the endpoints. RTL reverses left/right keys. Disabled fields cannot change value.

```php
FlexSlider::make('quantity')
    ->range(1, 100)
    ->step(1)
    ->largeStep(10)
    ->required()
    ->rules(['integer']);
```

Validation runs when the form is submitted, even if someone changes the request manually. Invalid numeric shapes, out-of-range values, incompatible steps, reversed constrained ranges, incorrect range handle counts and invalid handle gaps are rejected. Add normal Laravel rules for your business requirements. With the direct PHP API, sliders are **required by default** and initially select the minimum unless you configure `default()`. Use `required(false)` to allow an empty saved value; the visual handle still needs a position, so it displays the minimum until a value is chosen. `default(null)` keeps the initial saved state empty.

## FLEX schema configuration

```php
[
    'type' => 'flex_slider',
    'slug' => 'volume',
    'label' => 'Volume',
    'default_value' => 64,
    'config' => [
        'min' => 0,
        'max' => 100,
        'step' => 1,
        'thumb_style' => 'circle',
        'elastic' => true,
        'tooltips' => true,
        'icon_button' => 'hugeicons-volume-high',
        'icon_button_active_icon' => 'hugeicons-volume-off',
        'icon_button_value' => 0,
        'icon_button_restore_value' => 50,
        'icon_button_label' => 'Mute',
        'icon_button_active_label' => 'Restore volume',
        'show_value' => true,
        'value_placement' => 'header',
        'suffix' => '%',
        'start_icon' => 'hugeicons-volume-low',
        'end_icon' => 'hugeicons-volume-high',
        'marks' => [0, ['value' => 50, 'label' => '50%'], 100],
        'detent_distance' => 7,
        'stretch_limit' => 26,
        'large_step' => 10,
        'haptics' => false,
    ],
];
```

<Note>
Schema defaults differ from the direct PHP API: `step` is `1`, `decimal_places` is `0`, `show_value` is `true`, and `auto_fill` / `fill_track` are `true`. For a fractional schema slider, set both `step` and `decimal_places`, for example `0.25` and `2`. The full schema key reference appears below.
</Note>

## Fill, colors and thumb visibility

`fillTrack()` fills from the minimum to a single handle. For multiple handles, pass one boolean per segment: before the first handle, between every pair, and after the last handle. There must be **one more segment than handles**.

<CodeGroup>
```php title="Single handle"
FlexSlider::make('completion')
    ->range(0, 100)
    ->default(35)
    ->fillTrack()
    ->fillColor('#8B5CF6')
    ->showValue()
    ->suffix('%');
```

```php title="Two handles"
FlexSlider::make('price_band')
    ->range(0, 1000)
    ->rangeHandles()
    ->default([200, 800])
    ->fillTrack([false, true, false])
    ->color('success')
    ->showValue();
```

```php title="Three handles"
FlexSlider::make('thresholds')
    ->range(0, 100)
    ->default([20, 50, 80])
    ->fillTrack([true, false, true, false])
    ->tooltips([true, false, true])
    ->showValue()
    ->valuePlacement('header');
```
</CodeGroup>

`autoFill()` chooses the segments for you: from the minimum for one handle, or between handles for multiple handles. An explicit `fillTrack()` array takes priority. `fillTrack(null)->autoFill(false)` removes the fill.

```php
FlexSlider::make('selection')
    ->range(0, 100)
    ->rangeHandles()
    ->default([25, 75])
    ->autoFill()
    ->color('info')
    ->thumbStyle('circle');
```

`fillColor()` overrides `color()`. Pass a valid CSS color such as `#14B8A6` or `var(--brand-accent)`; `fillColor(null)` returns to the selected Filament color. `variant('secondary')` uses a neutral accent when no custom fill color is supplied. Light and dark appearances follow the panel theme.

```php
FlexSlider::make('opacity')
    ->range(0, 100)
    ->default(80)
    ->variant('secondary')
    ->size('sm')
    ->hideThumbUntilInteraction()
    ->fillTrack();
```

`hideThumbUntilInteraction()` hides a **single** handle until hover, focus or dragging. Multiple handles remain visible so users can see the selected intervals. Turn it off with `hideThumbUntilInteraction(false)`.

## Scale labels: every pips mode

Pips describe the scale; magnetic `marks()` are selectable presets. These are separate features and can be combined. All modes below use `PipsMode` from `Filament\Forms\Components\Slider\Enums`.

| Mode | Labels on the scale |
| --- | --- |
| `Range` | Boundaries and non-linear breakpoints; the default when calling `pips()` |
| `Steps` | Values based on the configured step |
| `Count` | A chosen number of evenly distributed positions, including both ends |
| `Positions` | Chosen positions expressed as percentages of the track |
| `Values` | Specific numeric values |

<Tabs>
<Tab title="Count">

```php
use Filament\Forms\Components\Slider\Enums\PipsMode;

FlexSlider::make('percentage')
    ->range(0, 100)
    ->step(5)
    ->default(50)
    ->pips(PipsMode::Count)
    ->pipsValues(5)
    ->pipsDensity(10)
    ->steppedPips()
    ->showValue()
    ->valuePlacement('header');
```

</Tab>
<Tab title="Positions">

```php
use Filament\Forms\Components\Slider\Enums\PipsMode;

FlexSlider::make('position')
    ->range(0, 100)
    ->step(1)
    ->pipsMode(PipsMode::Positions)
    ->pipsValues([0, 25, 50, 75, 100])
    ->pipsDensity(10);
```

</Tab>
<Tab title="Values">

```php
use Filament\Forms\Components\Slider\Enums\PipsMode;

FlexSlider::make('capacity')
    ->range(0, 1000)
    ->nonLinearPoints(['50%' => 100])
    ->pips(PipsMode::Values)
    ->pipsValues([0, 100, 500, 1000])
    ->pipsDensity(10);
```

</Tab>
<Tab title="Custom labels">

```php
use Filament\Forms\Components\Slider\Enums\PipsMode;

use Filament\Support\RawJs;

FlexSlider::make('capacity')
    ->range(0, 1000)
    ->step(100)
    ->pips(PipsMode::Values)
    ->pipsValues([0, 250, 500, 750, 1000])
    ->steppedPips()
    ->pipsFormatter(RawJs::make('`${$value} GB`'))
    ->pipsFilter(RawJs::make('$value % 500 === 0 ? 1 : -1'));
```

</Tab>
</Tabs>

`pipsDensity()` controls spacing between unlabelled scale ticks, as a percentage of track length; smaller values add more ticks. If omitted, it uses 10. `steppedPips()` aligns requested positions with permitted steps. `pipsFormatter()` receives the numeric `$value` and returns its display text. `pipsFilter()` also receives `$value`: return `-1` to omit a tick, `0` for an unlabelled tick, `1` for a major labelled tick, or `2` for a minor labelled tick. These formatters change presentation only.

For `Count`, use 2–1001 labelled positions. `Steps`, `Positions` and `Values`: use no more than 1001 configured positions. Keep labels sparse enough to remain readable on small screens. Disable the scale with `pipsMode(null)`; clear custom formatting with `pipsFormatter(null)` and `pipsFilter(null)`.

## Vertical controls and RTL

```php
FlexSlider::make('level')
    ->range(0, 100)
    ->default(60)
    ->vertical()
    ->rtl()
    ->thumbStyle('circle')
    ->fillTrack()
    ->tooltips()
    ->extraAttributes([
        'style' => '--fff-flex-slider-vertical-h: 14rem;',
    ]);
```

The default vertical track height is `10rem`. The example overrides its height to `14rem`. For vertical controls, `rtl(true)` puts the minimum at the bottom; `rtl(false)` puts it at the top. For horizontal controls, it puts the minimum on the right or left respectively. `rtl(null)` follows the panel's direction for horizontal fields and defaults to a bottom minimum for vertical fields. A larger keyboard increment still respects bounds and handle constraints.

## Dynamic configuration and state changes

Use PHP closures for values that depend on another field. If another field affects slider configuration, make that field live so the configuration is refreshed.

```php
use Filament\Schemas\Components\Utilities\Get;

FlexSlider::make('allowance')
    ->minValue(0)
    ->maxValue(fn (Get $get): int => $get('premium') ? 1000 : 100)
    ->step(1)
    ->default(25)
    ->trackLabel('Allowance')
    ->showValue()
    ->valuePlacement('header')
    ->disabled(fn (Get $get): bool => ! $get('enabled'));
```

When bounds change, also update a dependent value that is no longer allowed. Displaying a handle within new bounds does not replace server validation of the stored state.

```php
FlexSlider::make('percentage')
    ->range(0, 100)
    ->step(1)
    ->default(25)
    ->live()
    ->afterStateUpdated(function (int|float|null $state): void {
        // React to the selected value here.
    });
```

Pointer changes update the visible control immediately. The form state is committed when the drag finishes; keyboard changes, preset labels and icon buttons also commit their selections. Cancelling a drag restores its starting value. `live()` sends committed changes to the server; without it, the value is sent with the form's next request.

For client-side reactions, the slider dispatches two bubbling value events and can report a managed callback error. Listen on a parent element so you can observe one or several sliders:

| Event | When it fires | Detail |
| --- | --- | --- |
| `fff-slider-input` | The numeric value changes during interaction | `value`: number or array of numbers |
| `fff-slider-commit` | A selection is committed | `value` and `source`: `pointer`, `keyboard`, `mark`, `button-activate` or `button-restore` |
| `fff-slider-error` | A managed interaction callback fails | `error`: the reported error |

```html
<div
    x-data
    x-on:fff-slider-input="updatePreview($event.detail.value)"
    x-on:fff-slider-commit="saveSelection($event.detail.value)"
>
    <!-- Render your Filament form here. -->
</div>
```

`updatePreview()` and `saveSelection()` are functions supplied by your application. A browser event does not replace PHP validation or authorize a database write. Error events report failures; they do not indicate a successful save.

## Saving numbers and ranges

<Tabs>
<Tab title="Single number">

Use a numeric column for a single value. Match its precision to your configured step.

```php
// Migration:
$table->decimal('discount', 5, 1)->default(0);

// Form:
FlexSlider::make('discount')
    ->range(0, 100)
    ->step(0.5)
    ->decimalPlaces(1)
    ->default(0)
    ->suffix('%');
```

</Tab>
<Tab title="Multiple values">

Use a JSON column and an array cast for multiple handles.

```php
// Migration:
$table->json('budget');

// Model:
class Product extends \Illuminate\Database\Eloquent\Model
{
    protected function casts(): array
    {
        return ['budget' => 'array'];
    }
}

// Form:
FlexSlider::make('budget')
    ->range(0, 1000)
    ->step(10)
    ->rangeHandles()
    ->default([200, 800])
    ->autoFill();
```

</Tab>
<Tab title="Optional value">

```php
// Migration:
$table->unsignedInteger('optional_limit')->nullable();

// Form:
FlexSlider::make('optional_limit')
    ->range(0, 100)
    ->step(1)
    ->required(false)
    ->default(null);
```

</Tab>
</Tabs>

`locale()` affects separators and grouping only; it does not translate captions, labels or icon-button text. Accepts tags such as `en-US`, `de-DE` and `pl_PL`. Omitting it keeps plain numbers without grouping. Locale-aware initial rendering requires PHP's `intl` extension; enable it for consistent localized output before and after initialization.

`decimalPlaces()` accepts 0–20 and sets rounding/display precision. It does not define increments: use `step()` for that. Choose a step compatible with your precision, such as `step(0.25)->decimalPlaces(2)`. Steps are multiples of the step value: with `range(1, 10)->step(2)`, permitted values are 2, 4, 6, 8 and 10. Set bounds and defaults on that same grid. A non-linear segment can have its own step, but submitted values must also pass the field's configured Laravel step rules.

Without `decimalPlaces()`, readouts display whole numbers for continuous sliders and use the fractional precision of `step()` when provided. This display rounding also applies to initial values and accessible value text; it does not round a continuous slider's stored value. Set `decimalPlaces()` explicitly when you need a fixed number of decimal places. Custom JavaScript tooltip formatters continue to receive the actual numeric value.

## Schema option reference

Use PHP configuration for advanced combinations such as non-linear scales, pips formatters and per-handle tooltip formats. The saved FLEX schema exposes the following slider options; JavaScript functions and PHP closures do not belong in JSON.

| Schema key | PHP equivalent | Values / default |
| --- | --- | --- |
| `min`, `max` | `range()` | 0, 100 |
| `step` | `step()` | Positive number; schema default 1 |
| `size` | `size()` | `sm`, `md`, `lg`; `ui.slider_size` when omitted, normally `md` |
| `variant` | `variant()` | `default`, `secondary`; default `default` |
| `show_value` | `showValue()` | Boolean; default `true` |
| `prefix`, `suffix` | `prefix()`, `suffix()` | Display strings |
| `track_label` | `trackLabel()` | Caption string |
| `hide_thumb_until_interaction` | `hideThumbUntilInteraction()` | Boolean |
| `show_step_dots` | `showStepDots()` | Boolean |
| `value_position` | `valuePosition()` | `start`, `center`, `end` |
| `value_placement` | `valuePlacement()` | `header`, `footer` |
| `is_range` | `rangeHandles()` | Exactly two handles; default `false` |
| `fill_track` | `fillTrack()` | Boolean for single-handle fill, or a segment boolean array; default `true` |
| `auto_fill` | `autoFill()` | Boolean; default `true` |
| `color` | `color()` | Filament color name |
| `fill_color` | `fillColor()` | CSS color |
| `decimal_places` | `decimalPlaces()` | 0–10 in saved schema; default `0`; PHP accepts 0–20 |
| `thumb_style` | `thumbStyle()` | `pill`, `circle` |
| `elastic` | `elastic()` | Boolean |
| `tooltips` | `tooltips()` | Boolean in saved schema |
| `haptics` | `haptics()` | Boolean |
| `stretch_limit` | `stretchLimit()` | 1–80 pixels |
| `detent_distance` | `detentDistance()` | 0–32 pixels |
| `large_step` | `largeStep()` | Positive numeric keyboard increment |
| `marks` | `marks()` | Up to 128 values or labelled values |
| `start_icon`, `end_icon` | `startIcon()`, `endIcon()` | Decorative icon identifiers |
| `icon_button` | `iconButton()` | Button icon identifier |
| `icon_button_active_icon` | `iconButtonActiveIcon()` | Icon at the target value |
| `icon_button_value` | `iconButtonValue()` | Numeric target; minimum if omitted |
| `icon_button_restore_value` | `iconButtonRestoreValue()` | Numeric initial fallback |
| `icon_button_position` | `iconButtonPosition()` | `start`, `end` |
| `icon_button_label` | `iconButtonLabel()` | Accessible button label |
| `icon_button_active_label` | `iconButtonActiveLabel()` | Accessible label at target |

`label`, `default_value`, `validation` and `is_required` are field-level properties outside `config`. Saved schema fields are optional by default; set `is_required: true` to require a value. This overrides the direct PHP slider's required default. With `is_range: true`, the builder supplies an initial pair at approximately 20% and 80% of the scale, respecting the step, unless you supply an array default. The PHP API's `rangeHandles()` instead uses the minimum and maximum when no pair is supplied.

When configuring a range through the schema, an explicit `auto_fill` takes priority over `fill_track`. Choose one of these keys to keep the intended appearance clear.

## Assets and motion

Slider assets load with the rendered field. Elastic motion loads only when `elastic()` is enabled; the range/constraint adapter loads for configurations that need it. Rolling digits share the numeric animation module used by NumberStepper. Reusing that module does not require adding a NumberStepper field.

Inactive tabs and closed modals load their slider when activated. When a field is removed, its gesture listeners, observers, pending frames and animations are disposed. Your application does not need to initialize the slider separately. See [asset loading](/docs/asset-loading) for deployment and shared-resource behavior.

## Configuration reference

| Option | Default | Purpose |
| --- | --- | --- |
| `range(0, 100)` / `minValue()` / `maxValue()` | 0–100 | Numeric bounds |
| `step()` | `null` | Permitted numeric increments |
| `thumbStyle()` | `pill` | `pill` or `circle` |
| `elastic()` | `false` | Elastic motion in either style |
| `iconButton()` | `null` | Optional circular button, independently configured from decorative icons |
| `iconButtonActiveIcon()` | Button icon | Icon while the target value is selected |
| `iconButtonValue()` | Minimum | Target numeric value |
| `iconButtonRestoreValue()` | Selectable midpoint or endpoint | Fallback when no previous value exists |
| `iconButtonPosition()` | `start` | Button before or after the track: `start` / `end` |
| `iconButtonLabel()` | Apply value | Accessible name before activation |
| `iconButtonActiveLabel()` | Restore previous value | Accessible name while the target is selected |
| `startIcon()` / `endIcon()` | `null` | Icons alongside either style |
| `showValue()` | `false` | Permanent number readout |
| `valuePlacement()` | `footer` | `header` or `footer` |
| `valuePosition()` | `end` | Footer alignment |
| `trackLabel()` | `null` | Track caption |
| `tooltips()` | `false` | Active-handle tooltip; boolean, per-handle array or `RawJs` |
| `marks()` | `[]` | Up to 128 magnetic marks and optional labels |
| `detentDistance()` | 7 | Magnet radius, 0–32 pixels |
| `stretchLimit()` | 26 | Maximum elastic extension, 1–80 pixels |
| `haptics()` | `false` | Optional supported touch vibration |
| `largeStep()` | `null` | Explicit larger keyboard increment |
| `prefix()` / `suffix()` | `null` | Display decorations |
| `locale()` / `decimalPlaces()` | `null` | Number formatting and precision |
| `size()` | `md` | `sm`, `md`, `lg` |
| `variant()` | `default` | `default`, `secondary` |
| `color()` | `primary` | Filament accent |
| `fillColor()` | `null` | Custom accent |
| `fillTrack()` | `null` | Filled segments; calling without arguments fills from minimum |
| `autoFill()` | `false` | Infer filled segments from handle count |
| `showStepDots()` | `false` | Bounded inside-track step dots |
| `hideThumbUntilInteraction()` | `false` | Reveal single thumb on hover, focus or drag |
| `rangeHandles()` | `false` | Exactly two stored values |
| `minDifference()` / `maxDifference()` | `null` | Handle-distance limits |
| `rangePadding()` | `null` | Reserved ends of the scale |
| `nonLinearPoints()` | `null` | Piecewise scale mapping |
| `rtl()` | inherited direction | Reversed horizontal interaction |
| `vertical()` | `false` | Vertical orientation |
| `behavior()` | `Tap` | Track and range interaction |
| `pips()` / `pipsMode()` | `null` | Enable a scale; calling `pips()` uses `Range` |
| `pipsValues()` | `null` | Position count, percentages or numeric values, according to the mode |
| `pipsDensity()` | `null` (10 when rendered) | Unlabelled tick spacing |
| `steppedPips()` | `false` | Snap requested scale positions to permitted steps |
| `pipsFormatter()` | `null` | Custom `RawJs` scale label formatter |
| `pipsFilter()` | `null` | Custom `RawJs` tick visibility/type filter |
| `default()` | Minimum | Initial form value; number, array or explicit `null` |
| `required()` | `true` | Require a saved value; disable with `required(false)` |
| `disabled()` | `false` | Prevent pointer, keyboard and preset changes |

Most PHP configuration methods accept closures for values that depend on the form. Shared field options such as `label()`, `disabled()`, `required()` and state update callbacks remain available. See the dynamic configuration and state changes examples above.
