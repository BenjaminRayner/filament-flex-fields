<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;

return [
    FlexDatePicker::make('empty')
        ->label('Optional empty date')
        ->placeholder('Choose a date'),
    FlexDatePicker::make('read_only')
        ->label('Read-only date')
        ->readOnly()
        ->default('2026-10-15'),
    FlexDatePicker::make('disabled')
        ->label('Disabled date')
        ->disabled()
        ->default('2026-10-15'),
];
