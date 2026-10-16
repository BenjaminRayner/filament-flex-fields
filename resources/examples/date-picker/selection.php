<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;

return [
    FlexDatePicker::make('date')
        ->label('Calendar selection')
        ->clearable()
        ->default('2026-10-15')
        ->helperText('Typing is disabled; clicking the whole input opens the calendar.'),
    FlexDateField::make('typed')
        ->label('Guided numeric date')
        ->displayFormat('d/m/Y')
        ->default('2026-10-15')
        ->helperText('Arrow keys adjust a segment; letters cannot become date values.'),
    FlexDatePicker::make('editable')
        ->label('Calendar + guided entry')
        ->editable()
        ->displayFormat('d/m/Y')
        ->default('2026-10-15')
        ->helperText('Use either the segments or the calendar.'),
];
