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
    ScheduleField::make('schedule__custom_slots')
        ->label('Split shift with lunch break')
        ->days(['mon'])
        ->timezone('Europe/Warsaw')
        ->maxSlots(6)
        ->default([
            'timezone' => 'Europe/Warsaw',
            'days' => [
                'mon' => [
                    'enabled' => true,
                    'slots' => [
                        ['from' => '09:00', 'to' => '12:00', 'type' => 'slot'],
                        ['from' => '12:00', 'to' => '13:00', 'type' => 'break'],
                        ['from' => '13:00', 'to' => '17:00', 'type' => 'slot'],
                    ],
                ],
            ],
        ])
        ->helperText('09:00–12:00, lunch 12:00–13:00, then 13:00–17:00.'),
    ScheduleField::make('schedule__overnight')
        ->label('Overnight shift')
        ->days(['mon'])
        ->timezone('Europe/Warsaw')
        ->allowCopyToWeekdays(false)
        ->default([
            'timezone' => 'Europe/Warsaw',
            'days' => [
                'mon' => [
                    'enabled' => true,
                    'slots' => [['from' => '22:00', 'to' => '06:00', 'overnight' => true, 'type' => 'slot']],
                ],
            ],
        ])
        ->helperText('22:00–06:00, with overnight enabled.'),
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
    ScheduleField::make('schedule__validation_overlap')
        ->label('Overlapping Monday slots')
        ->days(['mon'])
        ->timezone('Europe/Warsaw')
        ->default([
            'timezone' => 'Europe/Warsaw',
            'days' => [
                'mon' => [
                    'enabled' => true,
                    'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']],
                ],
            ],
        ]),
    ScheduleField::make('schedule__validation_order')
        ->label('Reversed daytime slot')
        ->days(['mon'])
        ->timezone('Europe/Warsaw')
        ->default([
            'timezone' => 'Europe/Warsaw',
            'days' => [
                'mon' => [
                    'enabled' => true,
                    'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']],
                ],
            ],
        ]),
    ScheduleField::make('schedule__required')
        ->label('Required availability')
        ->days(['mon'])
        ->timezone('Europe/Warsaw')
        ->required(),
    ScheduleField::make('schedule__validation_count')
        ->label('At most two slots')
        ->days(['mon'])
        ->timezone('Europe/Warsaw')
        ->minSlots(1)
        ->maxSlots(2)
        ->default([
            'timezone' => 'Europe/Warsaw',
            'days' => [
                'mon' => [
                    'enabled' => true,
                    'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']],
                ],
            ],
        ]),
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
