<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;

return [
    TimezoneField::make('date_time__timezone')
        ->label('Timezone')
        ->defaultTimezone('Europe/Warsaw')
        ->default('Europe/Warsaw')
        ->helperText('Search IANA timezone identifiers using FLEX Select.'),
    ScheduleField::make('date_time__schedule')
        ->label('Recurring availability')
        ->days(['mon', 'tue', 'wed'])
        ->timeStep(15)
        ->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue', 'wed']))
        ->helperText('Enable days, add slots and choose their timezone.'),
];
