<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

return [
    FlexDateRangeField::make('required')
        ->label('Required date range')
        ->required()
        ->rangePresets([]),
    FlexDateRangeField::make('validation_order')
        ->label('Reversed range')
        ->rangePresets([])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
    FlexDateRangeField::make('validation_partial')
        ->label('Incomplete range')
        ->rangePresets([])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
];
