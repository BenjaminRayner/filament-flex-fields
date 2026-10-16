---
title: "Date range picker"
description: "Configure range shortcuts, one/two calendars, local times and strict endpoint validation."
icon: "calendar"
"canonical": "https://flex-fields.bjanczak.com/docs/date-range-picker"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/date-range-picker.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Date range picker in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/date-range-picker-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/date-range-picker-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/date-range-picker.webp"
"twitter:image:alt": "Date range picker in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/date-range-picker#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/date-range-picker",
      "name": "Date range picker",
      "description": "Configure range shortcuts, one/two calendars, local times and strict endpoint validation.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-range-picker#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/date-range-picker#article",
      "url": "https://flex-fields.bjanczak.com/docs/date-range-picker",
      "headline": "Date range picker",
      "description": "Configure range shortcuts, one/two calendars, local times and strict endpoint validation.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-range-picker#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/date-range-picker-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/date-range-picker-motion.mp4#video",
      "name": "Date range picker — FLEX Fields demonstration",
      "description": "A recorded demonstration of Date range picker in the FLEX Fields playground for Filament. Configure range shortcuts, one/two calendars, local times and strict endpoint validation.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/date-range-picker.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT11.617S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/date-range-picker-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/date-range-picker#article"
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
  src="/art/previews/date-range-picker-motion.mp4"
  poster="/art/previews/date-range-picker.webp"
  width="1920"
  height="1080"
  aria-label="Date range picker — FLEX Fields demonstration"
>
  <a href="/art/previews/date-range-picker-motion.mp4">Watch the Date range picker demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`FlexDateRangeField` stores an ordered pair of dates, optionally with local times. The panel maintains a draft until Apply; Cancel, Escape and an outside click discard that draft.

## Basic period

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

FlexDateRangeField::make('period')
    ->label('Report period')
    ->default(['start' => '2026-10-10', 'end' => '2026-10-15']);
```

The saved value is an array with `start` and `end`. An empty field saves `null`. The default day-only English label condenses dates, for example `Oct 10 – 15, 2026`. Custom formats and editable fields show both complete endpoints.

## Default shortcuts

Without `rangePresets()`, the panel offers Today, Yesterday, Last 7 days, Last 30 days, This month, Last month, This quarter and Year to date. Last 7/30 days include Today; This month/quarter end at Today. Last month includes that entire previous month. These reporting periods use Gregorian dates.

## Remove the shortcut panel

```php
FlexDateRangeField::make('period')->rangePresets([]);
```

This removes the complete shortcut rail, including its mobile row. Calendar selection and Apply/Cancel remain available.

## Your own periods

```php
FlexDateRangeField::make('campaign')
    ->rangePresets([
        ['label' => 'Launch week', 'start' => '2026-10-12', 'end' => '2026-10-18'],
        ['label' => 'Autumn campaign', 'start' => '2026-10-01', 'end' => '2026-11-30'],
    ]);
```

The list replaces the built-in shortcuts. Each item needs a nonempty label and valid ordered `Y-m-d` endpoints. Up to 30 custom presets are accepted. Presets do not bypass bounds or availability; Apply stays disabled for an invalid selection.

## Dynamic periods

```php
use Carbon\CarbonImmutable;

FlexDateRangeField::make('report')
    ->rangePresets(function (): array {
        $today = CarbonImmutable::today('Europe/Warsaw');

        return [
            ['label' => 'Today', 'start' => $today->toDateString(), 'end' => $today->toDateString()],
            ['label' => 'Next 14 days', 'start' => $today->toDateString(), 'end' => $today->addDays(13)->toDateString()],
        ];
    });
```

A PHP closure is evaluated when the field is rendered. To restore built-ins, use `rangePresets(null)`. If a page stays open across midnight, refresh the field/page to refresh its reporting shortcuts.

## One or two months

<CodeGroup>
```php Responsive default
FlexDateRangeField::make('period')->calendarMonths('auto');
```
```php One month
FlexDateRangeField::make('period')->calendarMonths(1);
```
```php Two months where space allows
FlexDateRangeField::make('period')->calendarMonths(2);
```
</CodeGroup>

Small viewports always use the compact one-month layout, even if two months are requested. The preset rail becomes a scrolling row. On short screens the calendar body scrolls while confirmation controls remain reachable. Input values stay within the field width; the panel shows a complete summary.

## Include start and end time

```php
FlexDateRangeField::make('booking')
    ->granularity('minute')
    ->hourCycle(12)
    ->minuteStep(15)
    ->timeZone('Europe/Warsaw')
    ->default([
        'start' => '2026-10-10T08:45:00',
        'end' => '2026-10-15T18:45:00',
    ]);
```

Clock controls share a row on desktop. On mobile their labels sit above each control, and the footer shows start and end on separate lines. All stored endpoints use the same `storageFormat()`.

```php
FlexDateRangeField::make('booking')->granularity('minute')->hourCycle(24)->hideTimeZone();
FlexDateRangeField::make('booking')->granularity('second')->showSeconds()->secondStep(5);
FlexDateRangeField::make('booking')->granularity('hour')->defaultTime('09:00');
```

Date-only is the default. `showSeconds()` promotes an already timed range to second precision; it does not turn a day-only range into a timed one.

## Available appointment hours

```php
FlexDateRangeField::make('booking')
    ->granularity('minute')
    ->allowedTimes(['09:00', '12:00', '17:00'])
    ->defaultTime('09:00');
```

Both endpoints must use one of those times. `minValue()`/`maxValue()` can additionally bound the complete date/time period. Use `disabledDates()` and `disabledWeekdays()` to block endpoint days.

## Range validation

```php
FlexDateRangeField::make('leave')
    ->required()
    ->minDate('2026-10-01')
    ->maxDate('2026-12-31')
    ->allowSameDay(false)
    ->disabledDates(['2026-12-25']);
```

Both endpoints must exist, be valid and meet the configured constraints. End cannot precede start. `allowSameDay(false)` requires different calendar days, including for timed ranges. Bounds are inclusive. Availability checks apply to endpoints; an unavailable day inside the period does not automatically invalidate it. Add a custom rule when every intervening day must be available.

## Minimum number of days

```php
use Carbon\CarbonImmutable;

FlexDateRangeField::make('stay')
    ->required()
    ->rules([
        function (string $attribute, mixed $value, Closure $fail): void {
            if (!is_array($value) || !isset($value['start'], $value['end'])) {
                return;
            }

            try {
                $days = CarbonImmutable::parse($value['start'])->diffInDays(CarbonImmutable::parse($value['end']), false);
            } catch (Throwable) {
                return;
            }

            if ($days < 2) {
                $fail('Choose a stay of at least two nights.');
            }
        },
    ]);
```

Built-in date validation still applies alongside your rule. Use the configured storage format when parsing custom formats in your own rules.

## Editable endpoints

```php
FlexDateRangeField::make('period')
    ->editable()
    ->displayFormat('d/m/Y')
    ->rangeSeparator(' – ');
```

Numeric segments follow your display format, including date/time segments when enabled. Arbitrary free text is not accepted as a date. An incomplete endpoint cannot submit successfully. On narrow inputs, scroll the segments or navigate them with the keyboard.

## Clearing and inline selection

```php
FlexDateRangeField::make('period')->clearable();
FlexDateRangeField::make('period')->inline()->rangePresets([]);
FlexDateRangeField::make('period')->fixedWeeks()->weekStartsOn(1);
```

Ranges are not clearable by default. Inline ranges also require Apply to confirm a draft; Cancel restores the previous value. The range grid displays days belonging to each shown month; `outsideDays()` applies to single-date calendars rather than adding duplicate days to a range grid.

## Appearance

```php
FlexDateRangeField::make('period')
    ->size('sm')
    ->variant('secondary')
    ->locale('pl')
    ->displayFormat('d.m.Y')
    ->weekStartsOn(1);
```

The trigger uses the shared input appearance. The range panel expands from that trigger and returns to it on closing, including when the input label is clipped. Reduced-motion preferences remove the spring morph. See [events](/docs/date-time-events-and-validation) to respond to confirmed changes.
