<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

return [
    FlexDateRangeField::make('one_month')
        ->label('One month')
        ->calendarMonths(1)
        ->clearable()
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
    FlexDateRangeField::make('two_months')
        ->label('Two months')
        ->calendarMonths(2)
        ->rangePresets([])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
    FlexDateRangeField::make('editable')
        ->label('Guided range entry')
        ->editable()
        ->displayFormat('d/m/Y')
        ->rangePresets([])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
];
