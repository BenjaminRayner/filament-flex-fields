<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Filament\Schemas\Components\Utilities\Set;

return [
    FlexDatePicker::make('event_date')
        ->label('Date with onChange')
        ->live()
        ->onChange(static fn (Set $set, ?string $state) => $set('event_value', $state ?? 'No date selected'))
        ->default('2026-10-15'),
    FlexTextInput::make('event_value')
        ->label('Last selected ISO value')
        ->readOnly()
        ->dehydrated(false)
        ->default('2026-10-15'),
];
