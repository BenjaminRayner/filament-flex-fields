<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeRangeField;

return [
    FlexTimeRangeField::make('range')
        ->label('Opening hours')
        ->hourCycle(24)
        ->hideTimeZone()
        ->minValue('08:00')
        ->maxValue('20:00')
        ->default(['start' => '09:00', 'end' => '17:00']),
    FlexTimeRangeField::make('range12')
        ->label('Opening hours · 12h')
        ->hourCycle(12)
        ->hideTimeZone()
        ->default(['start' => '09:00', 'end' => '17:00']),
    FlexTimeRangeField::make('range_slots')
        ->label('Only approved endpoints')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:00', '12:00', '17:00'])
        ->default(['start' => '09:00', 'end' => '17:00']),
];
