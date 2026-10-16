---
title: "Status Chip Column"
description: "Display a saved status as a compact, read-only chip in a Filament table, with configurable sizes and colors."
icon: "tag"
---

`StatusChipColumn` presents a status value in a Filament table. It reads the record's state; it does not edit or persist the status.

## Add a status column

```php
use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\StatusChipColumn;

StatusChipColumn::make('status')
    ->label('Status')
    ->chipSize(ControlSize::Md)
    ->chipColor('warning');
```

Use `ControlSize::Sm`, `ControlSize::Md` or `ControlSize::Lg` for the chip size. Use `chipColor('success')`, `warning`, `danger` or `info` for the built-in light-theme palettes. The default chip uses the primary palette; dark mode uses a neutral surface and light text.

## Supported state

A non-empty string becomes the chip label. An array can supply a label and its own color:

```php
// Model attribute or accessor result:
['label' => 'Published', 'color' => 'success']
```

The array's `color` takes precedence over `chipColor()`. If it has no color, the configured column color is used. `name` is accepted as an alternative to `label`. Null, empty labels and unsupported state types render no chip. Labels are escaped by the Blade renderer.

For a raw database status, map the value to a display label without changing the stored value:

```php
StatusChipColumn::make('status')
    ->state(fn ($record): array => match ($record->status) {
        'published' => ['label' => __('Published'), 'color' => 'success'],
        'review' => ['label' => __('In review'), 'color' => 'warning'],
        default => ['label' => __('Draft')],
    })
    ->chipSize(ControlSize::Sm);
```

Import `StatusChipColumn` and `ControlSize` as shown in the first example. Display labels belong to your application's translations.

## PHP options

| Method | Purpose |
| --- | --- |
| `chipSize(string \| ControlSize \| Closure $size)` | Set the chip size; default `md`. |
| `chipColor(string \| Closure \| null $color)` | Set the fallback color when the state does not provide one. |

Both options support Filament's closure evaluation for record-dependent configuration. The column acquires its declared FLEX styles through its own table-cell asset boundary.

## Other table presentations

Columns belonging to a field family are documented with that family:

- [Progress Column](/docs/progressbar)
- [Signature Preview Column](/docs/signaturefield)
- [Map Pin Column](/docs/mappickerfield)
- [User Column](/docs/userselect)
- [Rating Column](/docs/ratingfield)
- [Icon Column](/docs/icon-picker-field)
