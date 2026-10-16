<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;

return [
    TimezoneField::make('timezone__no_offset')
        ->label('Without offset label')
        ->showOffset(false)
        ->default('Europe/Warsaw'),
    TimezoneField::make('timezone__localized')
        ->label('Polski')
        ->locale('pl')
        ->default('Europe/Warsaw'),
    TimezoneField::make('timezone__lg')
        ->label('Large timezone input')
        ->size('lg')
        ->default('Asia/Tokyo'),
];
