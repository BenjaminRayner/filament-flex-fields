<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

return [
    ScheduleField::make('schedule__read_only')
        ->label('Read-only schedule')
        ->days(['mon', 'tue'])
        ->readOnly()
        ->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue'])),
    ScheduleField::make('schedule__disabled')
        ->label('Disabled schedule')
        ->days(['mon', 'tue'])
        ->disabled()
        ->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue'])),
];
