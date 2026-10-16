<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexTimeField::make('clock24')
        ->label('Time · 24h')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default('14:30'),
    FlexTimeField::make('clock12')
        ->label('Time · 12h')
        ->hourCycle(12)
        ->hideTimeZone()
        ->default('14:30'),
    FlexTimeField::make('seconds')
        ->label('Time with seconds')
        ->hourCycle(24)
        ->hideTimeZone()
        ->showSeconds()
        ->secondStep(5)
        ->default('14:30:15'),
];
