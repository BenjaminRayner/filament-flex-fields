---
title: "Events & validation"
description: "Respond to date/time changes and combine built-in checks with your application rules."
icon: "bolt"
---

## PHP change callbacks

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Filament\Schemas\Components\Utilities\Set;

FlexDatePicker::make('start_date')
    ->live()
    ->onChange(function (Set $set, ?string $state): void {
        $set('end_date', $state);
    });
```

`onChange()` uses the regular Filament state-update lifecycle. `live()` sends the change to PHP immediately. Without it, the normal field binding/request timing applies. The callback receives the field state in its configured saved format; ranges receive an endpoint array.

## Native-compatible callback API

```php
FlexDatePicker::make('date')
    ->live()
    ->afterStateUpdated(function (?string $state, ?string $old): void {
        // Respond to the new date and previous field value.
    });
```

Use normal Filament closure injection (`$state`, `$old`, `Get`, `Set`, the component or record) where supported. Programmatically filling a field does not automatically behave like a user change.

## Dependent date limits

```php
use Filament\Schemas\Components\Utilities\Get;

return [
    FlexDatePicker::make('starts_on')->live()->required(),
    FlexDatePicker::make('ends_on')
        ->minDate(fn (Get $get) => $get('starts_on'))
        ->required(),
];
```

Updating the start date refreshes the second field's lower bound. An existing end date below the new limit remains invalid until corrected; it is not silently rewritten.

## Range state

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

FlexDateRangeField::make('period')
    ->live()
    ->onChange(function (?array $state): void {
        $start = $state['start'] ?? null;
        $end = $state['end'] ?? null;
        // Recompute a report filter using the confirmed selection.
    });
```

Calendar range changes are confirmed by Apply. Hovering a day, moving the cursor or cancelling a draft does not replace the stored range. Guided manual editing updates its segments as the visitor types; an incomplete draft must still be rejected by validation.

## Built-in checks

| Value | Checks |
| --- | --- |
| Date | Valid year/month/day, leap years, bounds, blocked weekdays/dates and custom availability |
| Date/time | Date checks plus time bounds and allowed-time membership |
| Date range | Complete endpoints, ordered values, same-day policy and endpoint constraints |
| Clock | Valid 00:00–23:59 clock, optional seconds, bounds and allowed-time membership |
| Time range | Complete same-day endpoints, order and clock constraints |
| Month/year | Valid Gregorian period and representative-date constraints |
| Duration | Nonnegative elapsed time, valid minutes/seconds and inclusive bounds |

`required()` checks empty scalar values and empty range endpoints. Optional empty values remain allowed. Invalid nonempty input is not a valid empty field.

## Business validation

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

FlexTimeField::make('time')
    ->hourCycle(24)
    ->rules([
        function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && preg_match('/^\d{2}:(\d{2})/', $value, $parts) && (int) $parts[1] % 10 !== 0) {
                $fail('Choose a time on a ten-minute boundary.');
            }
        },
    ]);
```

This adds a divisibility rule on top of normal clock validation. For a fixed appointment list, `allowedTimes()` is simpler.

## Custom error text

```php
FlexDatePicker::make('date')
    ->required()
    ->validationAttribute('appointment date')
    ->segmentInvalidMessage('Enter a complete valid appointment date.');
```

The malformed-value message is distinct from bound/availability errors. Translate package messages in your application if you want to change their wording consistently. `isInvalid()` changes visual state only; it does not replace validation.

## Browser events

Listen on the field wrapper or an ancestor. Events bubble and are scoped to the control that emitted them. Browser listeners do not invoke PHP callbacks by themselves.

| Event | When | Detail |
| --- | --- | --- |
| `fff-date-open` | Calendar becomes open | `value` |
| `fff-date-close` | Calendar closes | `value` |
| `fff-date-input` | Numeric segment edit or direct clearing | `value`, `canonical`, `origin`, `valid`, `error` |
| `fff-date-change` | Calendar selection, completed segment change or clearing | `value`, `canonical`, `origin` |
| `fff-time-change` | Clock value committed or cleared | `value` |
| `fff-duration-input` | Duration text changes | `value`, `seconds`, `valid` |
| `fff-duration-change` | Valid duration changes or increment action | `value`, `seconds` |

`value` uses the saved format; `canonical` is the normalized day/local date-time representation. Partial segment input may carry an incomplete marker in `value` and `valid: false`; use change/validation to act on completed selections. PHP can still receive incomplete drafts when a field is live, so validate before performing business actions.

```javascript
const form = document.querySelector('#booking-form');

form.addEventListener('fff-date-change', (event) => {
    console.log(event.detail.value);
});

form.addEventListener('fff-time-change', (event) => {
    console.log(event.detail.value);
});
```

```javascript
form.addEventListener('fff-date-input', (event) => {
    if (!event.detail.valid) return;
    // Update a local preview for a complete numeric date.
});
```

Remove listeners when your application removes their owner. These are application events, not a separate upload/booking API.

## Reservation safety

Date availability is a selection aid. Another visitor can book the same slot after your calendar loaded. Recheck the date, slot, authorization and capacity in the save transaction. Timezone offsets and daylight-saving ambiguities must be resolved according to your application's policy when converting local date/time values to instants.
