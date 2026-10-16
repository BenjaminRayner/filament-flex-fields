---
title: "SwitchField"
description: Boolean toggle with row and card layouts, badges, and ripple effects.
icon: "toggle-right"
"canonical": "https://flex-fields.bjanczak.com/docs/switchfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/switch.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "SwitchField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/switch-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/switch-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/switch.webp"
"twitter:image:alt": "SwitchField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/switchfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/switchfield",
      "name": "SwitchField",
      "description": "Boolean toggle with row and card layouts, badges, and ripple effects.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/switchfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/switchfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/switchfield",
      "headline": "SwitchField",
      "description": "Boolean toggle with row and card layouts, badges, and ripple effects.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/switchfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/switch-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/switch-motion.mp4#video",
      "name": "SwitchField — FLEX Fields demonstration",
      "description": "A recorded demonstration of SwitchField in the FLEX Fields playground for Filament. Boolean toggle with row and card layouts, badges, and ripple effects.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/switch.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT4.133S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/switch-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/switchfield#article"
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
  src="/art/previews/switch-motion.mp4"
  poster="/art/previews/switch.webp"
  width="1920"
  height="1080"
  aria-label="SwitchField — FLEX Fields demonstration"
>
  <a href="/art/previews/switch-motion.mp4">Watch the SwitchField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Modern boolean toggle designed for settings pages and feature flags. Unlike the native Filament toggle, `SwitchField` supports description text, status badges, and two distinct layouts: a compact **row** and a bordered **card**.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField` |
| **State type** | `bool` — `true` or `false` |
| **Model cast** | `'is_active' => 'boolean'` · `'notifications_enabled' => 'boolean'` |
| **FieldType** | `switch` |
| **Playground** | `switch` slug in Flex Fields playground |
| **Default layout** | `row` |
| **Default state** | `false` |

---

### Basic usage

#### Simple row toggle
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;

SwitchField::make('is_active')
    ->label('Active status')
    ->description('Enable or disable this feature.');
```

#### Bordered card with badge
```php
SwitchField::make('pro_features')
    ->label('Pro Features')
    ->description('Unlock advanced analytics and reporting.')
    ->layout('card')
    ->badge('PREMIUM')
    ->badgeColor('warning')
    ->color('success');
```

---

### State & validation

#### Stored value
The field stores a boolean `true` or `false`. New forms start at `false` unless you set `default(true)`.

```php
$record->is_active; // bool — true
```

#### Validation rules
Validation runs when the form is submitted. Supported boolean representations are `true`, `false`, `1`, `0`, `'1'` and `'0'`; saved values are canonical booleans. A `null` state is treated as `false`. Arrays, nested data, arbitrary strings, decimals and other numbers are rejected, including for an optional switch. Use standard Filament/Laravel rules for additional requirements:

| Rule | Method | Description |
|------|--------|-------------|
| `accepted` | `accepted()` | Must be `true` (useful for Terms of Service) |
| `declined` | `declined()` | Must be `false` |
| `boolean` | (default) | Must be boolean |

```php
SwitchField::make('terms')
    ->label('I accept the terms')
    ->accepted();
```

---

### Layouts & Positioning

#### Layout: Row (default)
Label and description on the left, toggle on the right. Best for dense settings lists.

```php
SwitchField::make('notifications')
    ->layout('row');
```

#### Layout: Card
Adds a border and padding around the entire field. Ideal for highlighting important options.

```php
SwitchField::make('dark_mode')
    ->layout('card');
```

#### Label position
`labelPosition()` sets the position of the label. `start` keeps the label first and the switch last; `end` places the switch before the label. Positions follow the document reading direction in RTL layouts.

```php
SwitchField::make('is_active')
    ->labelPosition('end'); // Switch first, then label
```

---

### Capsule motion and keyboard

Press and hold the switch: its capsule stretches toward the destination while its outer edge remains anchored. Releasing toggles the state and lets the capsule travel on a spring. A keyboard or assistive click without a held press adds a brief stretch pulse. Animation adapts to `sm`, `md` and `lg`, including inline and card layouts.

- **Space:** hold to stretch; release to toggle.
- **Enter:** toggle the focused switch. Repeated keydown events do not repeatedly toggle it.
- **Tab:** move between available switches; disabled switches are skipped.
- Leaving the button, pointer cancellation, blur or a changed external state clears the held appearance.
- With reduced motion enabled, the capsule moves immediately; stretch, pulse and ripple effects are disabled.

The whole row or card is clickable. `inline()` renders only the switch, while `inlineWithLabel()` places it beside the normal form label:

```php
SwitchField::make('notifications')
    ->label('Notifications')
    ->inlineWithLabel()
    ->size('sm');
```

The default state binding is deferred until the next form request. To update the server after each toggle, enable `live()`:

```php
SwitchField::make('notifications')
    ->live()
    ->afterStateUpdated(function (bool $state): void {
        // React to the validated boolean in your form workflow.
    });
```

`afterStateUpdated()` is a change callback, not a substitute for submission validation. In custom forms, use `$this->form->getState()` before saving. Disabled fields are not normally included in saved form data.

The switch emits bubbling browser `input` and `change` events with `event.detail.checked` after user activation. Pointer gestures, streams or listeners are never shared between instances. No custom animation loop runs while idle.

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `variant(string\|Closure $variant)` | Setup | `'default'` | `default` (bordered) or `secondary` (filled) |
| `layout(string\|Closure $layout)` | Setup | `'row'` | `row` or `card` |
| `labelPosition(string\|Closure $pos)` | Setup | `'start'` | `start` or `end` |
| `description(string\|Closure\|null $text)` | Setup | `null` | Sub-label text |
| `color(string\|Closure\|null $color)` | Setup | `'primary'` | Toggle color when ON |
| `badge(string\|Closure\|null $badge)` | Setup | `null` | Optional text badge |
| `badgeColor(string\|Closure $color)` | Setup | `'primary'` | Badge color |
| `ripple(bool\|Closure $condition)` | Setup | `false` | Enable Material-style ripple |
| `compact(bool\|Closure $condition)` | Setup | `false` | Reduce padding |
| `inline(bool\|Closure $condition)` | Setup | `false` | Render without block wrapper |
| `inlineWithLabel(bool\|Closure $cond)` | Setup | `false` | Position toggle next to label |
| `size(string\|Closure $size)` | Setup | `'md'` | `sm`, `md`, `lg` |
| `onColor(string\|Closure $color)` | Setup | — | Inherited from Filament |
| `offColor(string\|Closure $color)` | Setup | — | Inherited from Filament |
| `onIcon(string\|Closure $icon)` | Setup | — | Inherited from Filament |
| `offIcon(string\|Closure $icon)` | Setup | — | Inherited from Filament |

---

### Real-world examples

#### Terms of Service (Wizard)
```php
Wizard\Step::make('Legal')
    ->schema([
        SwitchField::make('tos_accepted')
            ->label('Terms of Service')
            ->description('I have read and agree to the user agreement.')
            ->layout('card')
            ->accepted()
            ->required(),
    ]);
```

#### Compact Settings List
```php
Section::make('Preferences')
    ->schema([
        SwitchField::make('email_notifs')->compact(),
        SwitchField::make('sms_notifs')->compact(),
        SwitchField::make('push_notifs')->compact(),
    ]);
```

---

### Playground

`/admin/flex-fields/switch`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [SegmentControl](/docs/segmentcontrol) | Choosing between 2+ distinct text options |
| [FlexChecklist](/docs/flexchecklist) | Multi-select boolean list |
| [ChoiceCards](/docs/choicecards) | Large visual selection cards |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-switch` | Root wrapper |
| `fff-switch--layout-row` | Row layout |
| `fff-switch--layout-card` | Card layout |
| `fff-switch--compact` | Compact mode |
| `fff-switch__label` | Main label |
| `fff-switch__description` | Description text |
| `fff-switch__control` | The toggle switch element |
| `fff-switch__badge` | Badge wrapper |
| `fff-switch__thumb` | Animated capsule |
