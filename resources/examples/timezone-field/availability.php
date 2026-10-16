<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;

return [
    TimezoneField::make('timezone__limited')
        ->label('Approved timezones')
        ->timezones(['Europe/Warsaw', 'Europe/Berlin', 'America/New_York', 'UTC'])
        ->default('America/New_York')
        ->helperText('Only these four identifiers are offered.'),
    TimezoneField::make('timezone__excluded')
        ->label('Exclude selected timezones')
        ->exceptTimezones(['America/New_York', 'Asia/Tokyo'])
        ->default('Europe/Warsaw')
        ->helperText('New York and Tokyo are removed.'),
    TimezoneField::make('timezone__no_utc')
        ->label('Without UTC')
        ->includeUtc(false)
        ->default('Europe/Warsaw')
        ->helperText('Canonical UTC is omitted.'),
];
