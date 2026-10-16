<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;

return [
    FlexDatePicker::make('polish')
        ->label('Polski · Monday first')
        ->locale('pl')
        ->displayFormat('d F Y')
        ->weekStartsOn(1)
        ->default('2026-10-15'),
    FlexDatePicker::make('sunday')
        ->label('English · Sunday first')
        ->locale('en-US')
        ->weekStartsOn(0)
        ->default('2026-10-15'),
    FlexDatePicker::make('rtl')
        ->label('Arabic · RTL')
        ->locale('ar')
        ->calendarSystem('gregory')
        ->default('2026-10-15'),
];
