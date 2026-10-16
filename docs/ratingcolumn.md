---
title: "RatingColumn"
description: Visual star or icon rating display for Filament tables with fractional fill support.
icon: "chart-no-axes-column"
---

[← Back to Table of Contents](/docs/index)

### Summary

A table column for displaying numeric ratings as a series of stars or custom icons. It supports fractional fills (e.g., 4.5 stars), custom icons, and color-coded states. Pair this with [RatingField](/docs/ratingfield) for a complete rating system.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Tables\Columns\RatingColumn` |
| **State type** | `float\|int\|null` |
| **Model cast** | `'rating' => 'float'` |
| **Playground** | `rating` → **Demo → Table column** |
| **Default max** | `5` |
| **Default icon** | `heroicon-s-star` |

---

### Basic usage

#### Standard 5-star rating
```php
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\RatingColumn;

RatingColumn::make('average_rating')
    ->label('Score');
```

#### Custom icons and colors
```php
RatingColumn::make('satisfaction')
    ->ratingIcon('heroicon-s-face-smile')
    ->ratingColor('success')
    ->stars(10);
```

---

### State & formatting

#### Stored value
The column expects a finite numeric value (integer, float or numeric string). Values exceeding the `stars()` count are capped at the maximum. Empty, negative, nonfinite or malformed values display an empty cell. Column formatting does not validate or write database values.

```php
$record->rating; // 4.5
```

#### Fractional Fills
`RatingColumn` automatically calculates fractional fills for non-integer values. A rating of `3.7` will show three full stars and one star filled to 70%.

#### Displaying the numeric value
By default, the numeric value is shown next to the icons. You can hide it if needed:

```php
RatingColumn::make('rating')
    ->showValue(false);
```

---

### Shared assets and table options

RatingColumn is registered as an independent column through the FLEX component definition API. It shares rating styles and icon markup with RatingField and loads no form JavaScript. Each rendered cell has its own asset boundary, including identical ratings on different records.

Normal TextColumn options remain available: `label()`, `sortable()`, `tooltip()`, `alignment()`, `toggleable()` and relationship paths follow Filament's table API. Configure rating appearance with `ratingColor()`, `ratingIcon()` and `ratingSize()`; these are separate from TextColumn's text-color/icon settings.

```php
RatingColumn::make('average_rating')
    ->label('Average rating')
    ->ratingColor('success')
    ->ratingSize('sm')
    ->sortable()
    ->tooltip('Average customer review score');
```

Use `criticalStyles()` only for a table visible in the initial page. Hidden tabs and closed modals should keep the normal lazy behavior.

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `stars(int\|Closure $count)` | Setup | `5` | Maximum number of icons to show, from 1 to 100 |
| `ratingColor(string\|Closure $color)` | Setup | `'warning'` | Icon fill color |
| `ratingIcon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | Setup | `star` | Filament icon string |
| `showValue(bool\|Closure $cond)` | Setup | `true` | Show numeric value text |
| `ratingSize(string\|ControlSize\|Closure $size)` | Setup | `'md'` | `sm`, `md`, `lg` |

---

### Real-world examples

#### Product Reviews Table
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('title'),
            RatingColumn::make('rating')
                ->stars(5)
                ->ratingColor(fn ($state) => $state >= 4 ? 'success' : 'warning')
                ->sortable(),
        ]);
}
```

---

### Playground

`/admin/flex-fields/rating?variant=table-column`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [RatingField](/docs/ratingfield) | Form input for collecting ratings |
| [NpsField](/docs/nps-field) | Survey-style NPS or Likert scales |
| [ProgressBar](/docs/progressbar) | Linear progress instead of icon rating |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-rating-column` | Root wrapper |
| `fff-rating__items` | Container for all icons |
| `fff-rating__item` | Individual icon container |
| `fff-rating__icon--empty` | Background (empty) icon |
| `fff-rating__icon-clip` | Foreground (filled) icon |
| `fff-rating__value` | Numeric value text |
