---
title: "Date & time configuration"
description: "Defaults, supported values, examples and scope for the complete date/time field API."
icon: "sliders"
---

This reference covers the date, date/time, range, clock, month, year and duration controls. Most options accept a closure as well as a literal value, allowing configuration to depend on another field or the current record.

## Common form options

| Option | Meaning | Example |
| --- | --- | --- |
| `make()` | Form state key | `FlexDatePicker::make('date')` |
| `label()` | Visible and accessible field name | `->label('Delivery date')` |
| `default()` | Initial value; range uses an endpoint array | `->default('2026-10-15')` |
| `required()` | Empty values cannot submit | `->required()` |
| `rules()` | Additional Laravel validation rules | `->rules([$customRule])` |
| `validationMessages()` | Override messages for your added rules | `->validationMessages(['required' => 'Choose a date.'])` |
| `validationAttribute()` | Friendly name in validation messages | `->validationAttribute('delivery date')` |
| `placeholder()` | Empty-state hint | `->placeholder('Choose a day')` |
| `helperText()` | Persistent guidance below the input | `->helperText('No weekend deliveries.')` |
| `disabled()` | Disable interaction | `->disabled()` |
| `readOnly()` | Prevent editing and calendar changes | `->readOnly()` |
| `visible()` / `hidden()` | Conditional visibility | `->visible(fn ($record) => $record !== null)` |
| `columnSpan()` / `columnSpanFull()` | Form layout | `->columnSpanFull()` |
| `dehydrated()` | Whether this value participates in form persistence | `->dehydrated(false)` |
| `live()` | Send changes to PHP immediately | `->live()` |
| `onChange()` | PHP state-update callback | `->onChange(fn ($state) => ...)` |
| `afterStateUpdated()` | Filament-compatible callback API | `->afterStateUpdated(fn ($state) => ...)` |
| `afterStateHydrated()` | Customize the value after form fill | `->afterStateHydrated(fn ($state) => ...)` |
| `dehydrateStateUsing()` | Customize saved output; replaces the field's normal formatter | `->dehydrateStateUsing(fn ($state) => ...)` |
| `extraAttributes()` | Attributes on the field wrapper | `->extraAttributes(['class' => 'appointment-control'])` |

These are regular Filament form fields. Use [events and validation](/docs/date-time-events-and-validation) for complete callback recipes. Avoid replacing the normal persistence formatter unless you deliberately want a different output contract.

## Appearance

| Option | Values / default | Effect |
| --- | --- | --- |
| `size()` | `sm`, `md`, `lg`; default `md` | Shared FLEX input size |
| `variant()` | `primary`, `secondary`, `flat`; default `primary` | Shared input surface style |
| `rounding()` | `none`, `sm`, `md`, `lg`, `xl`, `full`, `native`; panel default when omitted | Input corner style |
| `focusOutline()` | Boolean; default off | Explicit input focus outline |
| `isInvalid()` | Boolean/closure/null; default null | Present an external invalid state; does not add validation rules |
| `segmentInvalidMessage()` | String/closure/null | Custom message for malformed temporal values and date-input feedback |

```php
FlexDatePicker::make('date')->size('sm')->variant('secondary')->rounding('lg');
FlexTimeField::make('time')->size('lg')->variant('flat')->focusOutline();
FlexDateRangeField::make('period')->isInvalid(fn () => false);
FlexDateField::make('date')->segmentInvalidMessage('Enter a complete valid date.');
```

Use `ControlSize::Sm`, `Md` or `Lg` if you prefer the size enum. Invalid variant names are configuration errors; `soft` is not a date/time variant.

## Formats, language and timezone

| Option | Default | Scope / meaning |
| --- | --- | --- |
| `locale()` | Application locale | Calendar labels, month names, direction and locale-derived first weekday |
| `timeZone()` | `app.timezone` | Calendar Today and timezone label; does not convert stored wall time |
| `displayFormat()` | Component-specific, see table below | PHP format for input display; explicit tokens control ordering and 12/24-hour rendering |
| `storageFormat()` | Component-specific | PHP format for persisted state |
| `hourCycle()` | `24` | `12` or `24` for clock/time display; duration remains elapsed hours |
| `forceLeadingZeros()` | `true` | Pads automatic 12-hour display; explicit format tokens take precedence |
| `monthDisplay()` | `numeric` | `numeric`, `short`, `long`; determines month-picker display and full-month date-only default |
| `showYearSegment()` | `true` | Month-picker display may hide the year; storage still includes it |
| `calendarSystem()` | Gregorian unless an explicit locale calendar extension is present | Full-date calendar grid system; see below |
| `hideTimeZone()` | `false`, except duration | Remove the timezone label |
| `hideTimeSection()` | `false` | Date/time or timed-range calendar hides its clocks but retains stored time |
| `rangeSeparator()` | ` - ` | Separator between fully displayed range endpoints; condensed English day ranges use their own dash |

| Control | Default display | Default storage |
| --- | --- | --- |
| Date picker / date input | `M j, Y` | `Y-m-d` |
| Date/time with minute precision | `M j, Y H:i` (24h) | `Y-m-d\TH:i:00` |
| Date/time with hour precision | `M j, Y H:i` (24h) | `Y-m-d\TH:00:00` |
| Date/time with second precision | `M j, Y H:i:s` (24h) | `Y-m-d\TH:i:s` |
| Day-only range | `M j, Y` per endpoint, with condensed English label when applicable | `Y-m-d` per endpoint |
| Timed range | Date/time formats above | Date/time formats above, per endpoint |
| Clock / time range | `H:i`; `h:i A` in automatic 12h mode | `H:i` |
| Clock with seconds | `H:i:s`; `h:i:s A` in automatic 12h mode | `H:i:s` |
| Month | `m/Y` | `Y-m` |
| Year | `Y` | `Y` |
| Duration | `H:i` | `H:i` |
| Duration with seconds | `H:i:s` | `H:i:s` |

```php
FlexDatePicker::make('date')->locale('de')->displayFormat('d.m.Y');
FlexDateTimePicker::make('starts_at')->hourCycle(12)->timeZone('Europe/London');
FlexDateRangeField::make('period')->locale('ar')->displayFormat('d/m/Y')->rangeSeparator(' – ');
FlexMonthPicker::make('month')->monthDisplay('long')->showYearSegment(false);
```

`displayFormat('H:i')` explicitly chooses 24-hour output even if `hourCycle(12)` is set; use `h:i A` for an explicit 12-hour format. Prefer full-year `Y` to the two-digit `y` when the century matters. Date/clock typed parsers use numeric ASCII values; guided input also normalizes supported localized decimal digits.

### Calendar systems

Full-date calendars use the systems available in `@internationalized/date`: Gregorian, Buddhist, Coptic, Ethiopic, Ethiopic Amete Alem, Hebrew, Indian, Islamic Civil, Islamic Tabular, Islamic Umm al-Qura, Japanese, Persian and ROC. Use supported identifiers such as `gregory`, `persian`, `indian`, `hebrew`, `islamic-civil` or `islamic-umalqura`. `gregorian` aliases `gregory`; `islamic` aliases `islamic-civil`.

```php
FlexDatePicker::make('date')->locale('fa')->calendarSystem('persian');
FlexDatePicker::make('date')->locale('hi-IN-u-ca-indian');
```

The grid can use another calendar, but stored/display-format numeric dates and guided date entry retain Gregorian meaning. The language does not itself change that storage contract. Month/year partial values require Gregorian calendar storage. Use a full-date picker when selecting through another calendar system.

## Precision and clock choices

| Option | Default / accepted values | Applies to |
| --- | --- | --- |
| `granularity()` | `day`, `hour`, `minute`, `second`, or `DateTimeGranularity` enum | Date/time and range: enables date-only or clock precision; clocks: hour granularity offers hourly choices |
| `showSeconds()` | Off | Clock/duration precision; promotes an already timed date/time/range to second precision |
| `minuteStep()` | `15`; positive divisor of 60 | Clock menus and date/time calendar clocks |
| `secondStep()` | `1`; positive divisor of 60 | Seconds menus |
| `defaultTime()` | `00:00:00`; first allowed time if a list exists and no explicit default was given | Candidate time when a date or empty clock is selected; not the whole field's `default()` |
| `allowedTimes()` | `null`; interval choices | Clock/date-time/timed range only. `[]` permits none; list restricts offered and accepted hours |
| `durationStep()` | `60` seconds | Duration increment buttons and arrow adjustment |

```php
FlexDateTimePicker::make('starts_at')->granularity('minute')->minuteStep(30)->defaultTime('09:00');
FlexDateTimePicker::make('starts_at')->showSeconds()->secondStep(5);
FlexDateRangeField::make('booking')->granularity('minute')->hourCycle(24);
FlexTimeField::make('time')->allowedTimes(['09:15', '11:40', '15:10']);
FlexDurationField::make('duration')->durationStep(900);
```

Steps control menu/button choices, not divisibility validation of all typed values. Use a custom rule or `allowedTimes()` if only exact values may submit. `allowedTimes()` on a day-only field is invalid configuration.

## Bounds and availability

| Option | Values | Meaning |
| --- | --- | --- |
| `minValue()` / `minDate()` | String, Carbon date, closure or null | Inclusive lower bound in the component's value type |
| `maxValue()` / `maxDate()` | String, Carbon date, closure or null | Inclusive upper bound |
| `disabledWeekdays()` | Integers 0–6; default `[]` | Block Sunday 0 through Saturday 6 |
| `disabledDates()` | `Y-m-d` strings; default `[]` | Up to 10,000 individual blackout dates |
| `isDateUnavailable()` | PHP closure receiving `Carbon $date` | Custom date/period availability; return true to block |
| `allowSameDay()` | `true` | Date ranges may start/end on the same civil day |

```php
FlexDatePicker::make('date')->minDate('2026-10-01')->maxDate('2026-10-31');
FlexDatePicker::make('date')->disabledWeekdays([0, 6])->disabledDates(['2026-10-16']);
FlexDateRangeField::make('stay')->allowSameDay(false);
FlexTimeField::make('time')->minValue('09:00')->maxValue('18:00');
FlexDurationField::make('duration')->minValue('00:15')->maxValue('100:00');
```

Invalid bounds are configuration errors. Clock bounds must be ordered within one day. Date availability callbacks belong to date/period fields, not clocks or durations. Range availability validates its endpoints; add your own business rule for unavailable interior days or minimum/maximum stay length.

## Calendar presentation

| Option | Default | Meaning |
| --- | --- | --- |
| `editable()` | Picker/range/clock off; `FlexDateField` on | Guided date segments or validated clock text |
| `inline()` | Off | Render the calendar persistently rather than in a dropdown |
| `clearable()` | Date/date-time/month/year on; range/clock off | Show clearing controls |
| `fixedWeeks()` | Single calendar on; range off | Six week rows, or only required rows |
| `outsideDays()` | On | Single-date calendar can show adjacent-month days; range grids keep their own month days |
| `highlightToday()` | On | Display today's marker |
| `showCalendarButton()` | Picker on; date input off | Show decorative trailing chevron; does not create a calendar on `FlexDateField` |
| `closeOnSelect()` | Single date/month/year on; date/time/range off | Single picker closes after selection. Range confirmation always uses Apply |
| `weekStartsOn()` / `firstDayOfWeek()` | Derived from locale | Week start: 0 Sunday, 1 Monday, through 6 Saturday |
| `calendarMonths()` | `auto` | Range only: 1, 2 or responsive auto; narrow screens fall back to 1 |
| `rangePresets()` | `null`, built-ins | Range only: `[]` hides shortcuts; custom list replaces them |

```php
FlexDatePicker::make('date')->inline()->fixedWeeks(false)->outsideDays(false);
FlexDatePicker::make('date')->highlightToday(false)->showCalendarButton(false);
FlexDateRangeField::make('period')->calendarMonths(1)->rangePresets([]);
FlexDateRangeField::make('period')->rangePresets([
    ['label' => 'Campaign', 'start' => '2026-10-01', 'end' => '2026-10-31'],
]);
```

## Recommended defaults

`withRecommendedDefaults()` applies a preset at the moment it is called. Options called afterwards override it. Put the preset first to keep your explicit hour cycle and precision.

| Mode | Preset |
| --- | --- |
| Date | Day precision; close on selection; highlight Today |
| Date/time | Minute precision; 24h; stay open after choosing a date |
| Date range | Day precision; same-day allowed; highlight Today; Apply confirmation |
| Clock | Minute precision; **12h**; timezone hidden |
| Time range | Minute precision; 24h; timezone hidden |
| Duration | Minute precision; elapsed hours; timezone hidden |
| Month/year | Close on selection; highlight Today |

```php
FlexTimeField::make('time')->withRecommendedDefaults()->hourCycle(24);
FlexDateRangeField::make('booking')->withRecommendedDefaults()->granularity('minute');
```

## Dynamic configuration

```php
use Filament\Schemas\Components\Utilities\Get;

FlexDatePicker::make('end_date')
    ->minDate(fn (Get $get) => $get('start_date'));
```

Make the dependency live if the calendar should refresh immediately when it changes. A field's dynamic rules are evaluated again during validation; presentation alone does not replace server checks.

## Choosing options for your form

<AccordionGroup>
  <Accordion title="I only need a day, with no manual typing">
    Use `FlexDatePicker`. Keep its default `editable(false)` and `Y-m-d` storage.
  </Accordion>
  <Accordion title="I need date and time with three exact slots">
    Use `FlexDateTimePicker`, `allowedTimes([...])`, and optionally `disabledWeekdays()`.
  </Accordion>
  <Accordion title="I need a range with no preset panel">
    Use `FlexDateRangeField::make('period')->rangePresets([])`.
  </Accordion>
  <Accordion title="I need an overnight period">
    Use a timed `FlexDateRangeField` with dates on both endpoints. A `FlexTimeRangeField` stays within one day.
  </Accordion>
  <Accordion title="I need more than 24 elapsed hours">
    Use `FlexDurationField`. A clock is always a time of day.
  </Accordion>
</AccordionGroup>
