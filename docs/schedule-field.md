---
title: "ScheduleField"
description: "Configure weekly opening hours, breaks, overnight shifts and time limits in Filament forms, with matching table and infolist displays."
icon: "calendar-days"
"canonical": "https://flex-fields.bjanczak.com/docs/schedule-field"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/schedule-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "ScheduleField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/schedule-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/schedule-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/schedule-field.webp"
"twitter:image:alt": "ScheduleField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/schedule-field#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/schedule-field",
      "name": "ScheduleField",
      "description": "Configure weekly opening hours, breaks, overnight shifts and time limits in Filament forms, with matching table and infolist displays.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/schedule-field#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/schedule-field#article",
      "url": "https://flex-fields.bjanczak.com/docs/schedule-field",
      "headline": "ScheduleField",
      "description": "Configure weekly opening hours, breaks, overnight shifts and time limits in Filament forms, with matching table and infolist displays.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/schedule-field#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/schedule-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/schedule-field-motion.mp4#video",
      "name": "ScheduleField — FLEX Fields demonstration",
      "description": "A recorded demonstration of ScheduleField in the FLEX Fields playground for Filament. Configure weekly opening hours, breaks, overnight shifts and time limits in Filament forms, with matching table and infolist displays.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/schedule-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.05S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/schedule-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/schedule-field#article"
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
  src="/art/previews/schedule-field-motion.mp4"
  poster="/art/previews/schedule-field.webp"
  width="1920"
  height="1080"
  aria-label="ScheduleField — FLEX Fields demonstration"
>
  <a href="/art/previews/schedule-field-motion.mp4">Watch the ScheduleField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)


### Summary

Weekly **availability / opening-hours editor** with per-day toggles, **from/to time slots**, optional **break** entries, **copy-to-weekdays**, searchable **timezone selector**, and **locked days**. From/To use the shared **FLEX Select** control. Default control size is **`sm`**; the timezone selector always renders at **`md`**.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField` |
| **State type** | `array&lt;timezone?: string, days: array&lt;string, array{enabled: bool, slots: list&lt;array{from: string, to: string, type?: string&gt;&gt;}&gt;}` |
| **Model cast** | `'opening_hours' =&gt; 'array'` or `'json'` |
| **FieldType** | `FieldType::Schedule` via Form Builder (`schedule` key) |
| **Playground** | `schedule-field` slug in Flex Fields playground |

---

### Basic usage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

ScheduleField::make('opening_hours')
    ->label('Opening hours')
    ->timezone('Europe/Warsaw')
    ->required();
```

Filament resource example:

```php
use Filament\Schemas\Schema;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        ScheduleField::make('opening_hours')
            ->label('Business hours')
            ->timezone('Europe/Warsaw')
            ->timeStep(15)
            ->minSlots(1)
            ->maxSlots(4)
            ->columnSpanFull(),
    ]);
}
```

---

### State format

Day keys use three-letter English abbreviations: **`mon`**, **`tue`**, **`wed`**, **`thu`**, **`fri`**, **`sat`**, **`sun`**.

Times are **`HH:MM`** strings in 24-hour format (`09:00`, `17:30`). On save, invalid slot entries are dropped and times are zero-padded.

#### Minimal example

```json
{
  "timezone": "Europe/Warsaw",
  "days": {
    "mon": {
      "enabled": true,
      "slots": [
        { "from": "09:00", "to": "17:00", "type": "slot" }
      ]
    },
    "sat": {
      "enabled": false,
      "slots": []
    }
  }
}
```

#### Split shift with lunch break

```json
{
  "timezone": "Europe/Warsaw",
  "days": {
    "mon": {
      "enabled": true,
      "slots": [
        { "from": "09:00", "to": "12:00", "type": "slot" },
        { "from": "12:00", "to": "13:00", "type": "break" },
        { "from": "13:00", "to": "17:00", "type": "slot" }
      ]
    }
  }
}
```

| Slot `type` | UI | Notes |
|-------------|-----|-------|
| `slot` (default) | Work slot row with briefcase icon | Counted toward min/max slot limits |
| `break` | Dashed break row with clock icon | Also validated for time order and overlap |

#### Overnight slots

Add `"overnight": true` when a work slot crosses midnight (e.g. `22:00` → `06:00`). The UI exposes an **Overnight** toggle on work slots; overlap validation uses the same `ScheduleV2` / `interval-engine` rules as PHP.

```json
{
  "from": "22:00",
  "to": "06:00",
  "type": "slot",
  "overnight": true
}
```

When `timezone(null)` hides the selector, omit the `timezone` key from state — it is not required or validated.

---

### Export & audit helpers

| Class | Purpose |
|-------|---------|
| `ScheduleExporter` | `toApi()` JSON payload + `toOpeningHoursSummary()` localized day periods |
| `ScheduleIcalGenerator` | Weekly `VEVENT` blocks with `RRULE:FREQ=WEEKLY;BYDAY=…` |
| `ScheduleChangeAuditor` | `diff()` + `describe()` human-readable schedule change trail |

```php
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleExporter;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleIcalGenerator;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleChangeAuditor;

$api = (new ScheduleExporter)->toApi($openingHours);
$ical = (new ScheduleIcalGenerator)->generate($openingHours, 'Store hours');
$changes = (new ScheduleChangeAuditor)->diff($before, $after);
$messages = (new ScheduleChangeAuditor)->describe($changes);
```

---

### Default state

The field default calls `ScheduleField::defaultSchedule()`:

```php
ScheduleField::defaultSchedule('Europe/Warsaw');
// Mon–Fri enabled 09:00–17:00, Sat/Sun disabled with empty slots
```

Static helper signature:

```php
/**
 * @param  list<string>|null  $days  Which days to include (default: all seven)
 * @return array<timezone?: string, days: array<string, array{enabled: bool, slots: list<array{from: string, to: string>>}>}
 */
ScheduleField::defaultSchedule(?string $timezone = null, ?array $days = null): array
```

Examples:

```php
// Weekdays only in state shape
ScheduleField::defaultSchedule('UTC', ['mon', 'tue', 'wed', 'thu', 'fri']);

// No timezone key (when selector hidden)
ScheduleField::defaultSchedule(null);

// Override field default explicitly
ScheduleField::make('hours')
    ->default(ScheduleField::defaultSchedule('America/New_York'));
```

---

### Configuration API

All methods accept `Closure` for dynamic configuration.

#### `days(array|Closure $days)`

Which days to render. Invalid day codes are ignored. Empty array falls back to all seven days.

Default: **`['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']`**.

```php
ScheduleField::make('hours')
    ->days(['mon', 'tue', 'wed', 'thu', 'fri']);

ScheduleField::make('hours')
    ->days(['mon', 'wed', 'fri']); // Mon / Wed / Fri only
```

#### `timezone(string|Closure|null $timezone)`

Default timezone identifier when the selector is shown, or **`null`** to hide the timezone block entirely.

Default: **`'UTC'`** (selector visible).

```php
ScheduleField::make('hours')->timezone('Europe/Warsaw');

ScheduleField::make('hours')->timezone(null); // local / implicit timezone

ScheduleField::make('hours')
    ->timezone(fn (): string => config('app.timezone'));
```

When shown, the selector uses the shared **TimezoneField** UI (search, UTC offset badge). It always renders at **`md`** size regardless of the schedule field `size()`.

#### `timeStep(int|Closure $minutes)`

Minute step for **FlexTimeSegmentsField** dropdowns (`From` / `To`). Clamped to **`1`–`60`**.

Default: **`5`**.

```php
ScheduleField::make('hours')->timeStep(15);
ScheduleField::make('hours')->timeStep(1);  // every minute
```

#### `minSlots(int|Closure $count)` / `maxSlots(int|Closure $count)`

Per-day slot limits (includes both work slots and breaks).

Defaults: **`1`** / **`10`**.

```php
ScheduleField::make('hours')
    ->minSlots(1)
    ->maxSlots(6);
```

#### `requireSlotsForEnabledDays(bool|Closure $condition = true)`

When **`true`** (default), each **enabled** day must have at least `minSlots()` valid entries.

When **`false`**, an enabled day may have zero slots (useful for “open but hours TBD” flows — use carefully).

```php
ScheduleField::make('hours')->requireSlotsForEnabledDays(false);
```

#### `allowCopyToWeekdays(bool|Closure $condition = true)`

Shows **Copy to weekdays** on the copy source day.

Default: **`true`**.

```php
ScheduleField::make('hours')->allowCopyToWeekdays(false);
```

#### `copySourceDay(string|Closure $day = 'mon')`

Which day row displays the copy button. Must be one of `mon` … `sun`.

Default: **`mon`**.

```php
ScheduleField::make('hours')->copySourceDay('tue');
```

#### `workdays(array|Closure $days)`

Target days for **Copy to weekdays**. Invalid entries are filtered. Empty after filter falls back to Mon–Fri.

Default: **`['mon', 'tue', 'wed', 'thu', 'fri']`**.

```php
ScheduleField::make('hours')
    ->copySourceDay('mon')
    ->workdays(['mon', 'tue', 'wed', 'thu', 'fri']);

ScheduleField::make('hours')
    ->workdays(['mon', 'wed', 'fri']); // copy only to these days
```

Copy replaces slots on target workdays with a clone of the source day's slots. It does not change `enabled` flags.

#### `lockedDays(array|Closure $days)`

Days that cannot be toggled on/off. Shows a **lock icon** instead of the switch. Locked days still display their schedule when enabled in state.

Only days present in `days()` are kept.

```php
ScheduleField::make('hours')->lockedDays(['sat', 'sun']);

ScheduleField::make('hours')
    ->days(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
    ->lockedDays(['sat', 'sun']);
```

#### `variant(string|Closure $variant)`

Visual container style.

| Value | Description |
|-------|-------------|
| `primary` | Default |
| `secondary` | Secondary surface |
| `soft` | Soft background tokens |
| `flat` | No shadow / minimal chrome |

```php
ScheduleField::make('hours')->variant('soft');
```

#### `size(string|ControlSize|Closure $size)`

Size of **time inputs** and day rows. Default: **`sm`**.

Timezone selector remains **`md`**.

```php
ScheduleField::make('hours')->size('md');
ScheduleField::make('hours')->size('lg');
```

#### `readOnly(bool|Closure $condition = true)` / `disabled(bool|Closure $condition = true)`

Inherited from Filament. Disables toggles, time pickers, copy, and slot toolbar actions.

```php
ScheduleField::make('hours')->readOnly();
ScheduleField::make('hours')->disabled();
```

#### `focusOutline(bool|Closure $condition = true)`

Inherited from `HasFieldFocusOutline`. Default: **`false`**. Adds focus ring on time input shells when enabled.

```php
ScheduleField::make('hours')->focusOutline();
```

---

### Public helper methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getRequiredValidationRule()` | `string\|Closure` | Returns `'nullable'` — emptiness checked inside schedule validator when `required()` is set |
| `getDays()` | `list&lt;string&gt;` | Normalized day codes to render |
| `showsTimezoneSelector()` | `bool` | Timezone block visible |
| `getDefaultTimezoneIdentifier()` | `string\|null` | Default / fallback timezone |
| `getTimeStep()` | `int` | Minute step (1–60) |
| `getMinSlots()` / `getMaxSlots()` | `int` | Slot limits |
| `shouldAllowCopyToWeekdays()` | `bool` | Copy button enabled |
| `getCopySourceDay()` | `string` | Copy source day code |
| `getWorkdays()` | `list&lt;string&gt;` | Copy target weekdays |
| `shouldRequireSlotsForEnabledDays()` | `bool` | Min slots enforced |
| `getLockedDays()` | `list&lt;string&gt;` | Locked day codes |
| `isDayLocked(string $day)` | `bool` | Single day lock check |
| `getVariant()` / `getSize()` | `string` | Resolved styling |
| `defaultSchedule(?string $timezone, ?array $days)` | `array` | Static default state builder |
| `normalizeState(mixed $state)` | `array` | Canonical schedule array |
| `isEmptyState(mixed $state)` | `bool` | True when no day keys |
| `stateMatches(array $normalized, mixed $state)` | `bool` | Compare normalized JSON |
| `getResolvedTimezoneIdentifiers()` | `list&lt;string&gt;` | Allowed IANA identifiers |
| `getTimezoneOptionsForJs()` | `list&lt;array&lt;id,label,offset&gt;&gt;` | Timezone dropdown data |
| `getAlpineConfiguration()` | `array` | Alpine + validation config |
| `getWrapperClasses()` | `array&lt;string, bool&gt;` | Root CSS class map |

---

### Validation

Built-in rule runs on submit (and when Livewire validates). Custom Filament `required` is mapped to **`nullable`** at rule level — emptiness is checked inside the schedule validator when `required()` is set.

| Check | When |
|-------|------|
| Required field | All configured days missing from state |
| Timezone required | Selector shown but value empty / invalid |
| Timezone in list | Must be in PHP `timezone_identifiers_list()` |
| Missing day | Configured day absent from state |
| Invalid slot shape | Non-array slot entry |
| Invalid time | Not parseable as `HH:MM` |
| `from` before `to` | End time must be after start |
| Min slots | Enabled day has fewer than `minSlots()` |
| Max slots | Enabled day exceeds `maxSlots()` |
| Overlap | Two slots on the same day overlap in time |

Real-time UI validation mirrors server messages via Alpine (`from_before_to`, `min_slots`, `max_slots`, `overlap`).

Disabled days skip slot validation entirely.

---

### Model & persistence

```php
// Migration
$table->json('opening_hours')->nullable();

// Model
protected $casts = [
    'opening_hours' => 'array',
];

protected $fillable = ['opening_hours'];
```

Loading existing state:

```php
ScheduleField::make('opening_hours')
    ->default(fn (?Location $record): array => $record?->opening_hours
        ?? ScheduleField::defaultSchedule('Europe/Warsaw'));
```

Reading normalized state in PHP:

```php
$normalized = ScheduleField::make('opening_hours')->normalizeState($rawState);
```

---

### Recipe: restaurant — weekdays + lunch break

```php
ScheduleField::make('opening_hours')
    ->label('Restaurant hours')
    ->timezone('Europe/Warsaw')
    ->days(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
    ->timeStep(15)
    ->minSlots(1)
    ->maxSlots(4)
    ->default([
        'timezone' => 'Europe/Warsaw',
        'days' => [
            'mon' => [
                'enabled' => true,
                'slots' => [
                    ['from' => '11:00', 'to' => '15:00', 'type' => 'slot'],
                    ['from' => '15:00', 'to' => '16:00', 'type' => 'break'],
                    ['from' => '16:00', 'to' => '22:00', 'type' => 'slot'],
                ],
            ],
            // ... other days
        ],
    ]);
```

Users can add breaks in the UI via **Add break**.

### Recipe: support desk — weekdays only, no weekends in UI

```php
ScheduleField::make('support_hours')
    ->label('Support availability')
    ->timezone('UTC')
    ->days(['mon', 'tue', 'wed', 'thu', 'fri'])
    ->copySourceDay('mon')
    ->workdays(['mon', 'tue', 'wed', 'thu', 'fri'])
    ->maxSlots(3);
```

### Recipe: always-closed weekends (locked)

```php
ScheduleField::make('store_hours')
    ->timezone('Europe/London')
    ->lockedDays(['sat', 'sun'])
    ->default(function (): array {
        $schedule = ScheduleField::defaultSchedule('Europe/London');
        $schedule['days']['sat']['enabled'] = false;
        $schedule['days']['sun']['enabled'] = false;

        return $schedule;
    });
```

### Recipe: no timezone selector (local hours)

```php
ScheduleField::make('local_hours')
    ->label('Local schedule')
    ->timezone(null)
    ->days(['mon', 'tue', 'wed', 'thu', 'fri']);
```

### Recipe: 30-minute steps, larger time inputs

```php
ScheduleField::make('clinic_hours')
    ->timeStep(30)
    ->size('md')
    ->variant('secondary');
```

### Recipe: read-only preview on view page

```php
ScheduleField::make('opening_hours')
    ->readOnly()
    ->default(fn ($record) => $record->opening_hours);
```

---

### Editing a schedule

Enable a day to reveal its periods. From and To use the FLEX Select control with your configured time step. Add working periods or breaks, reorder them, or remove periods while respecting your slot limits. Overnight periods can finish on the next day. Copying a day's periods to your chosen workdays keeps their existing open/closed status.

---

### Responsive layout

The editor adapts to its own width. Inside a narrow card or on a phone, From and To use two equal columns with their labels above the controls. Large controls retain their size without forcing the card wider than its container. Text direction follows your page unless you set `rtl()` explicitly.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [Date & time fields](/docs/date-and-time-fields) | Single date/time pickers, not weekly schedules |
| [TimezoneField](/docs/timezonefield) | Standalone IANA timezone selector |
| [FlexTimeSegments](/docs/time-picker#compact-api) | Inline HH:MM segments inside other fields |

---


## Table column

Use the same JSON attribute in a table. Only enabled days containing a working slot appear as circular badges. Hover a badge or focus it with the keyboard to see the day, all periods and timezone. A closed schedule shows “Closed” without inactive badges.

```php
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ScheduleColumn;

ScheduleColumn::make('opening_hours')
    ->label('Business hours')
    ->locale('en')
    ->hourCycle(24);
```

## Infolist entry

```php
use Bjanczak\FilamentFlexFields\Filament\Infolists\Components\ScheduleEntry;

ScheduleEntry::make('opening_hours')
    ->label('Opening hours')
    ->heading('Business hours')
    ->description('Opening and closing times.')
    ->showClosedDays()
    ->showTimezone();
```

By default the entry lists all seven days and shows the saved timezone when present. Hide closed days or timezone independently:

```php
ScheduleEntry::make('opening_hours')
    ->heading('Available hours')
    ->showClosedDays(false)
    ->showTimezone(false)
    ->hourCycle(12)
    ->locale('pl')
    ->days(['mon', 'wed', 'fri']);
```

Both displays accept `days()` in the order you want, `locale()`, `hourCycle(12|24)` and `showBreaks(false)`. Configuration values can be closures for per-record settings. Breaks appear with a separate label by default; a day containing only breaks does not count as open. Overnight periods include `(+1)` for the following day. Multiple periods are retained. Heading and description are optional and escaped as text. Use the `array` model cast for `opening_hours`; JSON strings and empty values are also handled.

The table and infolist are read-only presentations. Configure editing and validation with `ScheduleField`. They share the saved `days`/`timezone` structure. They do not infer holiday exceptions or perform availability/reservation checks.


## Week order, language and text direction

Use `weekStartsOn(0)` for Sunday first or `weekStartsOn(1)` for Monday first. Without this option, `days()` preserves your chosen order. The option works on the field, table column and infolist entry. It changes presentation, not the stored day keys.

```php
ScheduleField::make('opening_hours')
    ->weekStartsOn(0)
    ->size('lg')
    ->variant('secondary');

ScheduleColumn::make('opening_hours')
    ->locale('pl')
    ->weekStartsOn(1)
    ->hourCycle(24);

ScheduleEntry::make('opening_hours')
    ->locale('ar')
    ->weekStartsOn(0)
    ->rtl()
    ->hourCycle(12);
```

Columns translate both the short weekday labels and the full names in their tooltips. Entries translate full weekday names. The field uses your application's translation language. `locale()` changes the language of a display; it does not change the stored schedule. `rtl()` enables right-to-left layout, `rtl(false)` sets left-to-right, and `rtl(null)` inherits the page direction. Hour values and timezone identifiers remain readable in either direction.

Table tooltips are centered badges without an arrow. They wrap long schedules, stay within the screen width, and appear on keyboard focus as well as hover. Only enabled days containing working slots produce day circles.

## Validation when saving

Use the normal Filament form save flow (`getState()`), which validates the submitted schedule before returning the value to save. Supported checks include required availability, timezone identifiers, missing days, hour and minute bounds, period order, per-day slot limits, overlapping periods, and overnight overlaps between adjacent days, including Sunday to Monday.

Submitted times must be hour/minute values such as `09:00` or `9:00`. Relative expressions, numeric timestamps and arbitrary text are rejected. Slot data must be a list; a slot type is `slot` or `break`. Invalid flags and malformed retained slots on closed days are rejected too. Closed days do not require a period. `requireSlotsForEnabledDays(false)` makes the minimum optional for enabled days; `maxSlots()` still applies.

```php
ScheduleField::make('opening_hours')
    ->required()
    ->minSlots(1)
    ->maxSlots(3)
    ->requireSlotsForEnabledDays()
    ->lockedDays(['sat', 'sun']);
```

`lockedDays()` protects each selected day's enabled/closed status both in the interface and during validation. Its time periods remain editable when the day is enabled. On edit, the protected status comes from the record's attribute; on create, it comes from the field default.

For a custom fill, nested data or storage outside the record attribute, supply the original schedule from your trusted storage:

```php
ScheduleField::make('opening_hours')
    ->lockedDays(['sat', 'sun'])
    ->lockedScheduleUsing(
        fn () => $businessHoursService->originalSchedule(),
    );
```

Keep that resolver independent of the submitted form data. Custom business rules can be added with Filament's `rules()` API. Model authorization, preventing concurrent reservation conflicts and validating writes made outside the Filament form remain responsibilities of your application.
