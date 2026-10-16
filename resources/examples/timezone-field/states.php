<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;

return [
    TimezoneField::make('timezone__secondary')
        ->label('Small · secondary')
        ->size('sm')
        ->variant('secondary')
        ->default('Europe/Warsaw'),
    TimezoneField::make('timezone__read_only')
        ->label('Read-only timezone')
        ->readOnly()
        ->default('Europe/London'),
    TimezoneField::make('timezone__disabled')
        ->label('Disabled timezone')
        ->disabled()
        ->default('Europe/London'),
];
