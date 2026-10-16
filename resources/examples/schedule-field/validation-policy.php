<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

return [
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
];
