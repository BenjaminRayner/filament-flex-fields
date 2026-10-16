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
    TimezoneField::make('timezone__no_offset')
        ->label('Without offset label')
        ->showOffset(false)
        ->default('Europe/Warsaw'),
    TimezoneField::make('timezone__localized')
        ->label('Polski')
        ->locale('pl')
        ->default('Europe/Warsaw'),
    TimezoneField::make('timezone__lg')
        ->label('Large timezone input')
        ->size('lg')
        ->default('Asia/Tokyo'),
    TimezoneField::make('timezone__required')
        ->label('Required timezone')
        ->required()
        ->validationMessages(['required' => 'Choose a timezone.']),
    TimezoneField::make('timezone__validation_list')
        ->label('Restricted timezone validation')
        ->timezones(['Europe/Warsaw', 'UTC'])
        ->in(['Europe/Warsaw', 'UTC'])
        ->default('Europe/Warsaw'),
    TimezoneField::make('timezone__validation_identifier')
        ->label('IANA identifier validation')
        ->rules(['timezone'])
        ->default('Europe/Warsaw'),
    TimezoneField::make('timezone__secondary')
        ->label('Small · secondary')
        ->size('sm')
        ->variant('secondary')
        ->default('Europe/Warsaw'),
    TimezoneField::make('timezone__read_only')
        ->label('Read-only timezone')
        ->readOnly()
        ->default('Europe/London'),
    TimezoneField::make('timezone__disabled')
        ->label('Disabled timezone')
        ->disabled()
        ->default('Europe/London'),
];
