<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;

return [
    FlexDurationField::make('duration')
        ->label('Beyond 24 hours')
        ->durationStep(900)
        ->minValue('00:15')
        ->maxValue('100:00')
        ->default('26:30')
        ->helperText('15-minute step buttons; maximum 100 hours.'),
    FlexDurationField::make('seconds')
        ->label('Duration with seconds')
        ->showSeconds()
        ->durationStep(5)
        ->default('02:30:15')
        ->helperText('Five-second step buttons.'),
    FlexDurationField::make('short')
        ->label('Meeting duration')
        ->durationStep(300)
        ->minValue('00:05')
        ->maxValue('04:00')
        ->default('00:45')
        ->helperText('Between five minutes and four hours.'),
];
