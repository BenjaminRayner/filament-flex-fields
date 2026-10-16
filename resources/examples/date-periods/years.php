<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexYearPicker;

return [
    FlexYearPicker::make('year')
        ->label('Year')
        ->minValue('2020')
        ->maxValue('2030')
        ->default('2026'),
    FlexYearPicker::make('year_future')
        ->label('Planning year')
        ->minValue('2026')
        ->maxValue('2040')
        ->default('2028'),
    FlexYearPicker::make('year_small')
        ->label('Small · secondary')
        ->size('sm')
        ->variant('secondary')
        ->default('2026'),
];
