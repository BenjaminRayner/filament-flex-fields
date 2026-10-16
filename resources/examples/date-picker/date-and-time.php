<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimePicker;

return [
    FlexDateTimePicker::make('datetime')
        ->label('Date and time · 24h')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default('2026-10-15T14:30:00'),
    FlexDateTimePicker::make('datetime12')
        ->label('Date and time · 12h')
        ->hourCycle(12)
        ->default('2026-10-15T14:30:00'),
    FlexDateTimePicker::make('seconds')
        ->label('Date and time with seconds')
        ->hourCycle(24)
        ->hideTimeZone()
        ->showSeconds()
        ->secondStep(5)
        ->default('2026-10-15T14:30:15'),
];
