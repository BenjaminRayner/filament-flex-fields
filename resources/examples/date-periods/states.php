<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexYearPicker;

return [
    FlexMonthPicker::make('read_only_month')
        ->label('Read-only month')
        ->readOnly()
        ->default('2026-10'),
    FlexYearPicker::make('disabled_year')
        ->label('Disabled year')
        ->disabled()
        ->default('2026'),
    FlexDurationField::make('disabled_duration')
        ->label('Disabled duration')
        ->disabled()
        ->default('26:30'),
];
