<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

return [
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
];
