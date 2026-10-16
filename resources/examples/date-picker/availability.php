<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Carbon\Carbon;

return [
    FlexDatePicker::make('bounded')
        ->label('October only')
        ->minDate('2026-10-01')
        ->maxDate('2026-10-31')
        ->default('2026-10-15')
        ->helperText('Dates outside October 2026 are disabled.'),
    FlexDatePicker::make('business_days')
        ->label('Weekdays and blackouts')
        ->disabledWeekdays([0, 6])
        ->disabledDates(['2026-10-16', '2026-10-23'])
        ->default('2026-10-15')
        ->helperText('Saturday, Sunday, October 16 and October 23 are unavailable.'),
    FlexDatePicker::make('availability')
        ->label('Custom availability')
        ->isDateUnavailable(static fn (Carbon $date): bool => $date->isFriday())
        ->default('2026-10-15')
        ->helperText('The PHP callback blocks every Friday.'),
];
