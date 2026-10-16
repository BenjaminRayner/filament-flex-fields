<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexDatePicker::make('date_time__date')
        ->label('Date picker')
        ->clearable()
        ->default('2026-10-15')
        ->helperText('Calendar selection; clear the value with ×.'),
    FlexDateRangeField::make('date_time__range')
        ->label('Date range picker')
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15'])
        ->helperText('Choose endpoints; Apply commits, Cancel keeps the previous value.'),
    FlexTimeField::make('date_time__time')
        ->label('Time picker')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default('14:30')
        ->helperText('24-hour display; stored as HH:mm.'),
];
