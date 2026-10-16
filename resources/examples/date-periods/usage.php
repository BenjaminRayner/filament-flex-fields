<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexYearPicker;

return [
    FlexMonthPicker::make('month')
        ->label('Month')
        ->displayFormat('F Y')
        ->locale('en')
        ->default('2026-10'),
    FlexMonthPicker::make('bounded_month')
        ->label('Months in 2026')
        ->minValue('2026-01')
        ->maxValue('2026-12')
        ->default('2026-10'),
    FlexMonthPicker::make('localized_month')
        ->label('Polski')
        ->locale('pl')
        ->displayFormat('F Y')
        ->default('2026-10'),
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
    FlexDurationField::make('duration')
        ->label('Beyond 24 hours')
        ->durationStep(900)
        ->minValue('00:15')
        ->maxValue('100:00')
        ->default('26:30')
        ->helperText('15-minute step buttons; maximum 100 hours.'),
    FlexDurationField::make('seconds')
        ->label('Duration with seconds')
        ->showSeconds()
        ->durationStep(5)
        ->default('02:30:15')
        ->helperText('Five-second step buttons.'),
    FlexDurationField::make('short')
        ->label('Meeting duration')
        ->durationStep(300)
        ->minValue('00:05')
        ->maxValue('04:00')
        ->default('00:45')
        ->helperText('Between five minutes and four hours.'),
    FlexMonthPicker::make('required_month')
        ->label('Required month')
        ->required(),
    FlexMonthPicker::make('validation_month')
        ->label('Bounded month')
        ->minValue('2026-01')
        ->maxValue('2026-12')
        ->default('2026-10'),
    FlexYearPicker::make('validation_year')
        ->label('Bounded year')
        ->minValue('2020')
        ->maxValue('2030')
        ->default('2026'),
    FlexDurationField::make('required')
        ->label('Required duration')
        ->required(),
    FlexDurationField::make('validation_max')
        ->label('Maximum four hours')
        ->minValue('00:05')
        ->maxValue('04:00')
        ->default('00:45'),
    FlexDurationField::make('validation_minutes')
        ->label('Invalid minutes')
        ->default('01:30'),
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
