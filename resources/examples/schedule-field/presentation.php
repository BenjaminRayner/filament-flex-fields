<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

return [
    ScheduleField::make('schedule__sm')
        ->label('Small · primary')
        ->days(['mon', 'tue'])
        ->size('sm')
        ->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue'])),
    ScheduleField::make('schedule__secondary')
        ->label('Large · secondary')
        ->days(['mon', 'tue'])
        ->size('lg')
        ->variant('secondary')
        ->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue'])),
];
