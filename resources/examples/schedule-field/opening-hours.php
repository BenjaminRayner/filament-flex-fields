<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

return [
    ScheduleField::make('schedule__default')
        ->label('Business hours')
        ->days(['mon', 'tue', 'wed', 'thu', 'fri'])
        ->workdays(['mon', 'tue', 'wed', 'thu', 'fri'])
        ->timeStep(15)
        ->maxSlots(6)
        ->default(ScheduleField::defaultSchedule('Europe/Warsaw', ['mon', 'tue', 'wed', 'thu', 'fri']))
        ->helperText('15-minute choices; weekdays only.'),
    ScheduleField::make('schedule__locked_weekends')
        ->label('Locked weekends')
        ->lockedDays(['sat', 'sun'])
        ->timeStep(30)
        ->default(ScheduleField::defaultSchedule('Europe/Warsaw', ['mon', 'tue', 'wed', 'thu', 'fri']))
        ->helperText('Saturday and Sunday cannot be changed.'),
];
