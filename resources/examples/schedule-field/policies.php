<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

return [
    ScheduleField::make('schedule__no_timezone')
        ->label('Local schedule')
        ->days(['mon', 'tue', 'wed'])
        ->timezone(null)
        ->allowCopyToWeekdays(false)
        ->default(ScheduleField::defaultSchedule(null, ['mon', 'tue', 'wed']))
        ->helperText('No timezone selector and no copy action.'),
    ScheduleField::make('schedule__copy')
        ->label('Copy Tuesday to workdays')
        ->days(['mon', 'tue', 'wed', 'thu', 'fri'])
        ->copySourceDay('tue')
        ->workdays(['wed', 'thu', 'fri'])
        ->default(ScheduleField::defaultSchedule('Europe/Warsaw', ['mon', 'tue', 'wed', 'thu', 'fri']))
        ->helperText('Tuesday is copied only to Wednesday, Thursday and Friday.'),
];
