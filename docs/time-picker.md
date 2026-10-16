---
title: "Time picker"
description: "Choose 12/24-hour times, seconds, appointment slots and same-day time ranges."
icon: "clock"
"canonical": "https://flex-fields.bjanczak.com/docs/time-picker"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/time-picker.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Time picker in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/time-picker-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/time-picker-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/time-picker.webp"
"twitter:image:alt": "Time picker in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/time-picker#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/time-picker",
      "name": "Time picker",
      "description": "Choose 12/24-hour times, seconds, appointment slots and same-day time ranges.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/time-picker#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/time-picker#article",
      "url": "https://flex-fields.bjanczak.com/docs/time-picker",
      "headline": "Time picker",
      "description": "Choose 12/24-hour times, seconds, appointment slots and same-day time ranges.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/time-picker#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/time-picker-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/time-picker-motion.mp4#video",
      "name": "Time picker — FLEX Fields demonstration",
      "description": "A recorded demonstration of Time picker in the FLEX Fields playground for Filament. Choose 12/24-hour times, seconds, appointment slots and same-day time ranges.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/time-picker.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.75S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/time-picker-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/time-picker#article"
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
  src="/art/previews/time-picker-motion.mp4"
  poster="/art/previews/time-picker.webp"
  width="1920"
  height="1080"
  aria-label="Time picker — FLEX Fields demonstration"
>
  <a href="/art/previews/time-picker-motion.mp4">Watch the Time picker demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`FlexTimeField` selects a local time of day. It does not store a date or timezone offset. Use `FlexDurationField` for elapsed hours beyond 24.

## Basic clock

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

FlexTimeField::make('time')
    ->label('Appointment time')
    ->hourCycle(24)
    ->hideTimeZone()
    ->default('14:30');
```

The default saved format is `H:i`. The menu opens from the whole readonly input, with standard FLEX dropdown motion.

## 12-hour display

```php
FlexTimeField::make('time')->hourCycle(12)->default('14:30');
```

The display becomes `02:30 PM`; storage remains `14:30`. `forceLeadingZeros(false)` uses `2:30 PM`. An explicit `displayFormat()` takes priority over the automatic format.

```php
FlexTimeField::make('time')
    ->hourCycle(12)
    ->forceLeadingZeros(false)
    ->hideTimeZone();
```

## Interval steps

```php
FlexTimeField::make('time')->minuteStep(5);
FlexTimeField::make('time')->minuteStep(30);
FlexTimeField::make('time')->granularity('hour');
```

Minute/second steps must be positive divisors of 60: `1`, `2`, `3`, `4`, `5`, `6`, `10`, `12`, `15`, `20`, `30` or `60`. The minute default is 15. Steps control menu options; an existing precise time is retained until changed. They are not an additional server-side divisibility rule.

## Restrict hours

```php
FlexTimeField::make('time')
    ->hourCycle(24)
    ->minValue('09:00')
    ->maxValue('18:00');
```

The bounds are inclusive and refer to one day. Min must not exceed max. `23:00` to `02:00` is not a same-day clock range; use a date/time range for an overnight period.

## Only specific times

```php
FlexTimeField::make('appointment')
    ->allowedTimes(['09:15', '11:40', '15:10'])
    ->defaultTime('09:15')
    ->required();
```

The list is sorted and deduplicated. Values use `HH:mm` or `HH:mm:ss`, regardless of display format. Up to 1,440 entries are accepted. Both typed input and form submission enforce membership.

```php
FlexTimeField::make('time')->allowedTimes([]);   // No available choices.
FlexTimeField::make('time')->allowedTimes(null); // Restore regular intervals.
```

Without an explicit `defaultTime()`, the first allowed time becomes the default candidate. An explicit default outside the list is invalid configuration. Empty choices show an empty state, and no time can pass validation.

## Seconds

```php
FlexTimeField::make('time')
    ->hourCycle(24)
    ->showSeconds()
    ->secondStep(5)
    ->default('14:30:15');
```

Seconds have a separate selector. The saved format becomes `H:i:s`. To offer exact second-bearing slots:

```php
FlexTimeField::make('time')
    ->showSeconds()
    ->allowedTimes(['09:15:10', '09:15:30', '11:40:00']);
```

Do not configure second-bearing allowed values with a format that discards seconds.

## Typed input

```php
FlexTimeField::make('time')
    ->editable()
    ->hourCycle(24)
    ->minValue('09:00')
    ->maxValue('18:00');
```

Enter a complete clock such as `14:30`, or open the menu with ArrowDown/F4. In 12-hour mode include AM/PM. Invalid text and out-of-range times show feedback and fail form validation. This clock input validates the complete text; guided date segments belong to date fields.

## Clearable and readonly

```php
FlexTimeField::make('time')->clearable();
FlexTimeField::make('time')->readOnly();
FlexTimeField::make('time')->disabled();
```

Clock clearing is off by default. Readonly/disabled fields cannot change from typing or menus. An optional empty clock saves `null`.

## Time range

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeRangeField;

FlexTimeRangeField::make('opening_hours')
    ->hourCycle(24)
    ->hideTimeZone()
    ->minValue('08:00')
    ->maxValue('20:00')
    ->default(['start' => '09:00', 'end' => '17:00']);
```

Both endpoints use the same bounds, steps, format and allowed-time list. End may equal start but cannot precede it. Clearing one endpoint leaves an incomplete range; complete both or clear both before submission.

```php
FlexTimeRangeField::make('shift')
    ->hourCycle(12)
    ->allowedTimes(['09:00', '12:00', '17:00'])
    ->editable()
    ->clearable();
```

## Compact API

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeSegmentsField;

FlexTimeSegmentsField::make('time')
    ->minuteStep(15)
    ->hourCycle(24)
    ->hideTimeZone()
    ->size('sm');
```

This class uses the same current time input and dropdown as `FlexTimeField`; it does not introduce another storage format.

## Display and saved format

```php
FlexTimeField::make('time')
    ->hourCycle(12)
    ->displayFormat('g:i A')
    ->storageFormat('H:i:s');
```

Hidden seconds remain preserved while the displayed value is untouched. A newly selected menu value uses the current seconds unless you explicitly choose another second value.

## Timezone label

```php
FlexTimeField::make('time')->timeZone('Europe/Warsaw');
FlexTimeField::make('time')->timeZone('Europe/Warsaw')->hideTimeZone();
```

Changing the label does not convert stored hours. Resolve local times to instants only after combining a date and timezone in your application.

## Keyboard

Enter/Space open a readonly clock. ArrowDown and F4 open either mode. Inside the list, ArrowUp/ArrowDown move between enabled options, Home/End move to the first/last, and Enter selects. Escape closes the list. Focus returns to the trigger after keyboard selection or dismissal.
