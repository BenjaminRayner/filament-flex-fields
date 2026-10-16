<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;

return [
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
];
