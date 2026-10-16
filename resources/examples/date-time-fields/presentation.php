<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;

return [
    FlexDatePicker::make('date_time__small')
        ->label('Small · primary')
        ->size('sm')
        ->default('2026-10-15'),
    FlexDatePicker::make('date_time__secondary')
        ->label('Medium · secondary')
        ->size('md')
        ->variant('secondary')
        ->default('2026-10-15'),
    FlexDatePicker::make('date_time__flat')
        ->label('Large · flat')
        ->size('lg')
        ->variant('flat')
        ->default('2026-10-15'),
];
