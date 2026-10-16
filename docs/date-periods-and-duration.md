---
title: "Months, years & duration"
description: "Choose partial calendar periods or elapsed time without wrapping at midnight."
icon: "hourglass-half"
"canonical": "https://flex-fields.bjanczak.com/docs/date-periods-and-duration"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/date-periods.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Months, years & duration in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/date-periods-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/date-periods-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/date-periods.webp"
"twitter:image:alt": "Months, years & duration in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/date-periods-and-duration#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/date-periods-and-duration",
      "name": "Months, years & duration",
      "description": "Choose partial calendar periods or elapsed time without wrapping at midnight.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-periods-and-duration#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/date-periods-and-duration#article",
      "url": "https://flex-fields.bjanczak.com/docs/date-periods-and-duration",
      "headline": "Months, years & duration",
      "description": "Choose partial calendar periods or elapsed time without wrapping at midnight.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-periods-and-duration#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/date-periods-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/date-periods-motion.mp4#video",
      "name": "Months, years & duration — FLEX Fields demonstration",
      "description": "A recorded demonstration of Months, years & duration in the FLEX Fields playground for Filament. Choose partial calendar periods or elapsed time without wrapping at midnight.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/date-periods.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.45S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/date-periods-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-periods-and-duration#article"
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
  src="/art/previews/date-periods-motion.mp4"
  poster="/art/previews/date-periods.webp"
  width="1920"
  height="1080"
  aria-label="Months, years & duration — FLEX Fields demonstration"
>
  <a href="/art/previews/date-periods-motion.mp4">Watch the Months, years & duration demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

## Month picker

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;

FlexMonthPicker::make('billing_month')
    ->label('Billing month')
    ->displayFormat('F Y')
    ->default('2026-10');
```

The month grid opens directly. Saved values use `Y-m`; display defaults to `m/Y`. A month represents the whole period, not an individual day.

## Month labels

```php
FlexMonthPicker::make('month')->monthDisplay('numeric');
FlexMonthPicker::make('month')->monthDisplay('short')->locale('pl');
FlexMonthPicker::make('month')->monthDisplay('long')->locale('pl');
FlexMonthPicker::make('month')->monthDisplay('long')->showYearSegment(false);
```

These produce numeric, abbreviated or full month names. Hiding the year changes display only; the saved month still includes a year. Keep the full year visible if the user must distinguish periods across years.

## Month bounds

```php
FlexMonthPicker::make('month')
    ->minValue('2026-01')
    ->maxValue('2026-12')
    ->required();
```

For period availability, a month is checked through its first day. A year is checked through January 1. Apply `isDateUnavailable()` to those representative dates if you need to disable whole periods. Month/year fields support Gregorian partial storage; alternate calendar systems are available on full-date calendars.

## Year picker

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexYearPicker;

FlexYearPicker::make('year')
    ->label('Reporting year')
    ->minValue('2020')
    ->maxValue('2030')
    ->default('2026');
```

The year grid opens directly. Display and storage default to `Y`. Years 1–9999 are supported.

## Guided period entry

```php
FlexMonthPicker::make('month')->editable()->displayFormat('m/Y');
FlexYearPicker::make('year')->editable();
```

Numeric segment editing follows the same keyboard behavior as full dates. `inline()`, `clearable()`, `closeOnSelect()`, `size()`, `variant()` and locale configuration are also available.

```php
FlexMonthPicker::make('month')->inline()->closeOnSelect(false);
FlexYearPicker::make('year')->clearable(false)->size('sm')->variant('secondary');
```

## Elapsed duration

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;

FlexDurationField::make('duration')
    ->label('Duration')
    ->default('26:30');
```

A duration has no date, timezone or AM/PM. Hours do not wrap at 24. Enter `HH:mm` or enable seconds for `HH:mm:ss`. Up to 99,999 hours are supported. Negative durations and minutes/seconds over 59 are invalid.

## Increment controls

```php
FlexDurationField::make('duration')
    ->durationStep(900)
    ->minValue('00:15')
    ->maxValue('100:00');
```

`durationStep()` is measured in seconds. The default is 60 seconds. Without visible seconds, the step must be a positive multiple of 60. Buttons and ArrowUp/ArrowDown adjust the value within the configured bounds.

## Seconds

```php
FlexDurationField::make('duration')
    ->showSeconds()
    ->durationStep(5)
    ->default('02:30:15');
```

Enabling seconds changes display/storage to `H:i:s`. Arbitrary display patterns and 12-hour formatting are not supported for elapsed duration.

## Keep stored seconds hidden

```php
FlexDurationField::make('duration')
    ->storageFormat('H:i:s')
    ->default('02:30:15');
```

Display remains `02:30`. Leaving this unchanged preserves the stored 15 seconds. Use `showSeconds()` if the visitor needs to inspect or edit them.

## Required and bounded

```php
FlexDurationField::make('duration')
    ->required()
    ->minValue('00:30')
    ->maxValue('08:00');
```

Bounds and formats are checked on form validation. An empty optional duration saves `null`; malformed text fails validation. Steps determine button increments rather than forcing typed values to that interval.

```php
FlexDurationField::make('duration')->disabled();
FlexDurationField::make('duration')->readOnly();
FlexDurationField::make('duration')->size('lg')->variant('flat')->focusOutline();
```

See [configuration](/docs/date-time-configuration) for the complete option list and [events](/docs/date-time-events-and-validation) for callbacks.
