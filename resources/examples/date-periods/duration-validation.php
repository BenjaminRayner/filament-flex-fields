<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;

return [
    FlexDurationField::make('required')
        ->label('Required duration')
        ->required(),
    FlexDurationField::make('validation_max')
        ->label('Maximum four hours')
        ->minValue('00:05')
        ->maxValue('04:00')
        ->default('00:45'),
    FlexDurationField::make('validation_minutes')
        ->label('Invalid minutes')
        ->default('01:30'),
];
