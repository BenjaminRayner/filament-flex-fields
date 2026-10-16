<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexTimeField::make('required')
        ->label('Required time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->required()
        ->validationMessages(['required' => 'Choose a start time.']),
    FlexTimeField::make('validation_bounds')
        ->label('Business-hour limit')
        ->hourCycle(24)
        ->hideTimeZone()
        ->editable()
        ->minValue('09:00')
        ->maxValue('18:00')
        ->default('14:30'),
    FlexTimeField::make('validation_slots')
        ->label('Whitelist validation')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->default('11:40'),
];
