<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexTimeField::make('interval')
        ->label('Five-minute menu')
        ->hourCycle(24)
        ->hideTimeZone()
        ->minuteStep(5)
        ->minValue('09:00')
        ->maxValue('18:00')
        ->default('14:30')
        ->helperText('Options from 09:00 to 18:00, every five minutes.'),
    FlexTimeField::make('slots')
        ->label('Only appointment times')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->default('11:40')
        ->helperText('09:15, 11:40 and 15:10 are the only allowed values.'),
    FlexTimeField::make('empty')
        ->label('No available hours')
        ->allowedTimes([])
        ->hideTimeZone()
        ->helperText('Empty state; no time can be selected.'),
];
