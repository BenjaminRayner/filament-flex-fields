<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;

return [
    TimezoneField::make('timezone__basic')
        ->label('Search all timezones')
        ->defaultTimezone('Europe/Warsaw')
        ->default('Europe/Warsaw')
        ->helperText('Stores Europe/Warsaw; offsets are shown in the menu.'),
    TimezoneField::make('timezone__empty')
        ->label('Optional timezone')
        ->helperText('Empty until a timezone is selected.'),
    TimezoneField::make('timezone__browser')
        ->label('Browser timezone')
        ->browserTimezoneDefault()
        ->browserTimezoneSortFirst()
        ->helperText('The browser timezone is preferred only while empty.'),
];
