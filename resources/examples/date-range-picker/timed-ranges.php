<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

return [
    FlexDateRangeField::make('timed')
        ->label('Range with time · 12h')
        ->granularity('minute')
        ->hourCycle(12)
        ->default(['start' => '2026-10-10T08:45:00', 'end' => '2026-10-15T18:45:00']),
    FlexDateRangeField::make('timed24')
        ->label('Range with time · 24h')
        ->granularity('minute')
        ->hourCycle(24)
        ->hideTimeZone()
        ->rangePresets([])
        ->allowedTimes(['09:00', '12:00', '17:00'])
        ->default(['start' => '2026-10-10T09:00:00', 'end' => '2026-10-15T17:00:00']),
    FlexDateRangeField::make('seconds')
        ->label('Timed range with seconds')
        ->granularity('second')
        ->hourCycle(24)
        ->hideTimeZone()
        ->rangePresets([])
        ->default(['start' => '2026-10-10T09:00:15', 'end' => '2026-10-15T17:00:30']),
];
