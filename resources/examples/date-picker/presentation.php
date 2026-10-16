<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;

return [
    FlexDatePicker::make('small')
        ->label('Small · primary')
        ->size('sm')
        ->default('2026-10-15'),
    FlexDatePicker::make('medium')
        ->label('Medium · secondary')
        ->size('md')
        ->variant('secondary')
        ->default('2026-10-15'),
    FlexDatePicker::make('large')
        ->label('Large · flat')
        ->size('lg')
        ->variant('flat')
        ->default('2026-10-15'),
];
