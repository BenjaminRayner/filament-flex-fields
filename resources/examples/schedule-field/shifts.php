<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

return [
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
];
