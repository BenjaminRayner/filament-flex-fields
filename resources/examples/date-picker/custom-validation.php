<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimePicker;

return [
    FlexDatePicker::make('validation_impossible')
        ->label('Impossible date')
        ->editable()
        ->displayFormat('d/m/Y')
        ->default('2026-10-15'),
    FlexDateTimePicker::make('validation_slots')
        ->label('Unavailable appointment time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->default('2026-10-15T11:40:00'),
    FlexDatePicker::make('validation_custom')
        ->label('Your own rule')
        ->rule('bail')
        ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
            if ((int) substr((string) $value, -2) % 2 !== 0) {
                $fail('Choose an even day of the month.');
            }
        })->default('2026-10-14'),
];
