<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeRangeField;

return [
    FlexTimeField::make('validation_clock')
        ->label('Malformed time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->editable()
        ->default('14:30'),
    FlexTimeRangeField::make('validation_order')
        ->label('Reversed time range')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default(['start' => '09:00', 'end' => '17:00']),
    FlexTimeRangeField::make('validation_partial')
        ->label('Incomplete time range')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default(['start' => '09:00', 'end' => '17:00']),
];
