<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

return [
    FlexDateRangeField::make('long_text')
        ->label('Long date labels')
        ->displayFormat('l, F j, Y')
        ->rangePresets([])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15'])
        ->helperText('Try this input at small viewport widths.'),
    FlexDateRangeField::make('polish')
        ->label('Polski · Monday first')
        ->locale('pl')
        ->displayFormat('d F Y')
        ->weekStartsOn(1)
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
    FlexDateRangeField::make('rtl')
        ->label('Arabic · RTL')
        ->locale('ar')
        ->weekStartsOn(0)
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
];
