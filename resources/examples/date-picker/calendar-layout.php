<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;

return [
    FlexDatePicker::make('inline')
        ->label('Inline calendar')
        ->inline()
        ->default('2026-10-15')
        ->helperText('Always visible; no dropdown is needed.'),
    FlexDatePicker::make('compact')
        ->label('Compact month')
        ->fixedWeeks(false)
        ->outsideDays(false)
        ->highlightToday(false)
        ->default('2026-10-15')
        ->helperText('No extra fixed rows or days from neighboring months.'),
    FlexDatePicker::make('stay_open')
        ->label('Keep calendar open')
        ->closeOnSelect(false)
        ->default('2026-10-15')
        ->helperText('Choose another day without opening the menu again.'),
];
