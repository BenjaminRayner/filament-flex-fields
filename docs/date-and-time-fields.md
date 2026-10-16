---
title: "Date & time"
description: "Choose the right control for dates, periods, appointment times and elapsed duration."
icon: "calendar"
---

Build forms for appointments, reports, bookings and schedules with a consistent set of date and time controls.

<CardGroup cols={2}>
  <Card title="Date picker" icon="calendar-day" href="/docs/date-picker">Single dates, date and time, guided typing and availability.</Card>
  <Card title="Date range picker" icon="calendar-week" href="/docs/date-range-picker">Start/end dates, shortcuts and optional times.</Card>
  <Card title="Time picker" icon="clock" href="/docs/time-picker">12/24-hour clocks, appointment slots and time ranges.</Card>
  <Card title="Months, years & duration" icon="hourglass-half" href="/docs/date-periods-and-duration">Period selection and elapsed time beyond 24 hours.</Card>
</CardGroup>

## Choose a component

| Component | Use it for | Default saved value |
| --- | --- | --- |
| `FlexDatePicker` | A single calendar day | `2026-10-15` |
| `FlexDateField` | Guided date entry without a calendar | `2026-10-15` |
| `FlexDateTimePicker` | A date and local time | `2026-10-15T14:30:00` |
| `FlexDateRangeField` | A period with two endpoints | `['start' => '2026-10-10', 'end' => '2026-10-15']` |
| `FlexTimeField` | A time of day | `14:30` |
| `FlexTimeSegmentsField` | The compact time-picker API | `14:30` |
| `FlexTimeRangeField` | Start/end times within one day | `['start' => '09:00', 'end' => '17:00']` |
| `FlexMonthPicker` | A month and year | `2026-10` |
| `FlexYearPicker` | A year | `2026` |
| `FlexDurationField` | Elapsed time | `26:30` |

A date is a calendar day. A clock is a local time of day. A duration is elapsed time and may exceed 24 hours. Date/time values do not include a UTC offset: choose a timezone explicitly when converting them to timestamps in your application.

## First form

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexDatePicker::make('appointment_date')->label('Appointment date')->required(),
    FlexDateRangeField::make('report_period')->label('Report period'),
    FlexTimeField::make('appointment_time')->label('Time')->hourCycle(24)->hideTimeZone(),
];
```

## Display and storage are separate

A visitor can see `Oct 15, 2026` while your record stores `2026-10-15`. Change `displayFormat()` for presentation and `storageFormat()` for persistence. Both use PHP date-format tokens. Keep year, month and day in a full-date storage format; use the month/year components for partial periods.

```php
FlexDatePicker::make('date')
    ->displayFormat('d/m/Y')
    ->storageFormat('Y-m-d')
    ->default('2026-10-15');
```

## Appearance

All controls use the FLEX text-input style. Choose `size('sm'|'md'|'lg')`, `variant('primary'|'secondary'|'flat')`, rounding and an optional focus outline. Light and dark appearances follow the panel; calendar selections use its primary color.

```php
FlexDatePicker::make('date')
    ->size('lg')
    ->variant('secondary')
    ->rounding('xl')
    ->focusOutline();
```

## Validation

The form checks required values, valid dates/times, configured limits, availability and range order. A half-completed range is not a valid value. Calendar restrictions also apply to manually entered values when the form is validated. Menu interval steps control offered options; use `allowedTimes()` if arbitrary off-step clock values must be rejected.

```php
FlexDatePicker::make('date')
    ->required()
    ->minDate('2026-10-01')
    ->maxDate('2026-10-31')
    ->disabledWeekdays([0, 6]);
```

## Accessibility and responsive behavior

Calendar triggers open with Enter, Space, ArrowDown or F4. Arrow keys navigate days; Home/End navigate a week; PageUp/PageDown navigate months and Shift moves by year. Escape closes a dropdown without confirming a range draft. Numeric date segments support arrow adjustment, deletion and automatic advancement.

Ranges use two months and a vertical shortcut panel on wide screens, then one month and horizontally scrolling shortcuts on narrow screens. Long readonly values are clipped inside the input; opening the panel reveals the selection. Guided segments scroll horizontally so every part remains reachable. Reduced-motion preferences suppress spring and slide effects.

<CardGroup cols={2}>
  <Card title="Configuration reference" icon="sliders" href="/docs/date-time-configuration">Defaults, scope and examples for every date/time option.</Card>
  <Card title="Events and validation recipes" icon="bolt" href="/docs/date-time-events-and-validation">PHP callbacks, browser events, dependent fields and validation.</Card>
</CardGroup>
