---
title: "Date picker"
description: "Configure single dates, date/time appointments, guided entry and unavailable days."
icon: "calendar"
"canonical": "https://flex-fields.bjanczak.com/docs/date-picker"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/date-picker.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Date picker in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/date-picker-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/date-picker-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/date-picker.webp"
"twitter:image:alt": "Date picker in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/date-picker#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/date-picker",
      "name": "Date picker",
      "description": "Configure single dates, date/time appointments, guided entry and unavailable days.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-picker#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/date-picker#article",
      "url": "https://flex-fields.bjanczak.com/docs/date-picker",
      "headline": "Date picker",
      "description": "Configure single dates, date/time appointments, guided entry and unavailable days.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-picker#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/date-picker-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/date-picker-motion.mp4#video",
      "name": "Date picker — FLEX Fields demonstration",
      "description": "A recorded demonstration of Date picker in the FLEX Fields playground for Filament. Configure single dates, date/time appointments, guided entry and unavailable days.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/date-picker.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.133S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/date-picker-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-picker#article"
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
  src="/art/previews/date-picker-motion.mp4"
  poster="/art/previews/date-picker.webp"
  width="1920"
  height="1080"
  aria-label="Date picker — FLEX Fields demonstration"
>
  <a href="/art/previews/date-picker-motion.mp4">Watch the Date picker demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Use `FlexDatePicker` to select a day, `FlexDateTimePicker` when the choice includes time, and `FlexDateField` for guided entry without a calendar.

## Basic date

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;

FlexDatePicker::make('date')
    ->label('Appointment date')
    ->default('2026-10-15')
    ->required();
```

The default display is `Oct 15, 2026`; storage is `2026-10-15`. Clicking the input opens the calendar. The trailing chevron is decorative. Picking a day closes the ordinary date picker by default.

## Guided entry

```php
FlexDatePicker::make('date')
    ->editable()
    ->displayFormat('d/m/Y');
```

Each date part is a separate numeric segment. Typing replaces the selected segment; completing it advances to the next. Letters cannot become date values. Month/year changes keep the day valid, including leap years. A partial date remains incomplete rather than silently retaining the previous selection.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateField;

FlexDateField::make('birthday')
    ->label('Birthday')
    ->displayFormat('d/m/Y')
    ->required();
```

`FlexDateField` has guided entry enabled and no calendar. ArrowUp/ArrowDown change a segment; Home/End select its minimum/maximum; Delete clears it. ArrowLeft/ArrowRight move between parts. F4 or Alt+ArrowDown opens an available calendar.

## Readonly, disabled and clearing

```php
FlexDatePicker::make('date')->editable(false);
FlexDatePicker::make('date')->clearable(false);
FlexDatePicker::make('date')->readOnly();
FlexDatePicker::make('date')->disabled();
```

`editable(false)` prevents manual entry but keeps calendar selection available. `readOnly()` prevents changes. `disabled()` disables the field; use Filament's persistence settings if you need a disabled value submitted. Date and date/time pickers are clearable by default; their calendar includes Clear.

## Earliest and latest date

```php
FlexDatePicker::make('delivery_date')
    ->minDate('2026-10-01')
    ->maxDate('2026-10-31');
```

Bounds are inclusive. `minValue()` and `maxValue()` are equivalent aliases. A Carbon date or closure can provide a dynamic bound.

```php
use Carbon\Carbon;

FlexDatePicker::make('delivery_date')
    ->minDate(fn () => Carbon::today())
    ->maxDate(fn () => Carbon::today()->addMonths(3));
```

## Block weekdays and individual days

```php
FlexDatePicker::make('appointment')
    ->disabledWeekdays([0, 6])
    ->disabledDates(['2026-12-24', '2026-12-25']);
```

Weekdays are `0` Sunday through `6` Saturday. Blackout dates use `Y-m-d`, also when a date/time picker stores a time. Duplicate dates are removed; use a callback instead of shipping more than 10,000 blackout dates.

## Custom availability

```php
use Carbon\Carbon;

FlexDatePicker::make('appointment')
    ->isDateUnavailable(fn (Carbon $date): bool => $date->isWeekend());
```

Return `true` for an unavailable date. The calendar checks the visible date window and shows loading/retry feedback if that request fails. Submission checks the selected date again. The callback is not a reservation lock: booking applications must check capacity again when saving.

## Always-visible calendar

```php
FlexDatePicker::make('date')
    ->inline()
    ->fixedWeeks(false)
    ->outsideDays(false);
```

`inline()` keeps the calendar visible. `fixedWeeks(false)` permits four, five or six week rows; the default is six. `outsideDays(false)` hides dates from adjacent months in the single-date calendar. `highlightToday(false)` removes today's marker.

```php
FlexDatePicker::make('date')
    ->closeOnSelect(false)
    ->showCalendarButton(false)
    ->highlightToday(false);
```

The calendar can stay open after selection. Hiding the chevron does not remove calendar selection from the input.

## Language, week order and formats

```php
FlexDatePicker::make('date')
    ->locale('pl')
    ->displayFormat('d F Y')
    ->weekStartsOn(1);
```

`locale()` translates month/day names and determines text direction and the default first weekday. It does not automatically replace an explicitly chosen format or the default English-style token order. Set `displayFormat()` for the order you want. `weekStartsOn(0)` starts with Sunday; `1` starts with Monday. `firstDayOfWeek()` is the equivalent API.

```php
FlexDatePicker::make('date')->locale('ar')->displayFormat('d/m/Y');
FlexDatePicker::make('date')->locale('en')->displayFormat('Y-m-d');
FlexDatePicker::make('date')->locale('fr')->displayFormat('j F Y');
```

The calendar supports RTL navigation and layout. See the [configuration reference](/docs/date-time-configuration#calendar-systems) for alternate calendar systems and storage semantics.

## Date and time

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimePicker;

FlexDateTimePicker::make('starts_at')
    ->label('Starts at')
    ->hourCycle(24)
    ->minuteStep(15)
    ->timeZone('Europe/Warsaw')
    ->default('2026-10-15T14:30:00');
```

The date/time picker stays open while choosing a date and a clock value. The timezone is a label and determines Today; the saved value is a local date/time without an offset.

<CodeGroup>
```php 12-hour display
FlexDateTimePicker::make('starts_at')->hourCycle(12);
```
```php 24-hour display
FlexDateTimePicker::make('starts_at')->hourCycle(24)->hideTimeZone();
```
```php Seconds
FlexDateTimePicker::make('starts_at')->showSeconds()->secondStep(5);
```
</CodeGroup>

`showSeconds()` enables second precision in date/time storage and display when time is enabled. For hour-only selection, use `granularity('hour')`; for seconds, use `granularity('second')`. `granularity('day')` removes time entirely.

## Appointment slots

```php
FlexDateTimePicker::make('appointment')
    ->hourCycle(24)
    ->allowedTimes(['09:15', '11:40', '15:10'])
    ->defaultTime('09:15')
    ->disabledWeekdays([0, 6]);
```

Only the specified hours pass validation. Use `allowedTimes([])` for no available hours, or `allowedTimes(null)` to return to the interval list. An explicitly configured default time must be in the allowed list.

## Date/time bounds

```php
FlexDateTimePicker::make('appointment')
    ->minValue('2026-10-15T09:00:00')
    ->maxValue('2026-10-20T17:00:00')
    ->minuteStep(30);
```

On the first/last date, only times within the corresponding bound are offered. To restrict opening hours on every day, use `allowedTimes()` as well; these two bounds describe a continuous period, not recurring business hours.

## Keep time hidden

```php
FlexDateTimePicker::make('starts_at')
    ->defaultTime('09:00')
    ->hideTimeSection();
```

This hides calendar clock controls while retaining time in the stored value. It does not convert the field to date-only; use `granularity('day')` for that.

## Custom saved format

```php
FlexDatePicker::make('date')
    ->displayFormat('d/m/Y')
    ->storageFormat('d.m.Y');
```

Use the same format when restoring saved values. Prefer `Y-m-d` for interoperable date storage and keep dates in the year range 1–9999.


## Saving to DATE and DATETIME columns

The displayed date is separate from the saved value. A date uses `Y-m-d`; date/time uses a local ISO date/time such as `2026-06-15T14:30:00` by default. For a Filament resource using Eloquent, define matching model casts:

```php
protected function casts(): array
{
    return [
        'starts_on' => 'date',
        'starts_at' => 'datetime',
    ];
}
```

```php
FlexDatePicker::make('starts_on')
    ->label('Start date');

FlexDateTimePicker::make('starts_at')
    ->label('Start date and time');
```

Eloquent converts the value to its database date format when saving and returns a Carbon instance when reading. Both pickers accept that instance when restoring the form. Use nullable database columns for optional fields.

For direct Query Builder writes, or when you need an explicit SQL date/time string, configure the saved format:

```php
FlexDateTimePicker::make('starts_at')
    ->storageFormat('Y-m-d H:i:s');
```

Date/time values represent local wall time. The picker does not infer the user's timezone or silently convert it to UTC. Apply the application's timezone policy before persistence when storing an instant. A range is an array of `start` and `end`: use a JSON attribute or explicitly map its endpoints to separate date columns.
