<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexTimeField::make('small')
        ->label('Small · primary')
        ->hourCycle(24)
        ->hideTimeZone()
        ->size('sm')
        ->default('14:30'),
    FlexTimeField::make('medium')
        ->label('Medium · secondary')
        ->hourCycle(24)
        ->hideTimeZone()
        ->size('md')
        ->variant('secondary')
        ->default('14:30'),
    FlexTimeField::make('large')
        ->label('Large · flat')
        ->hourCycle(24)
        ->hideTimeZone()
        ->size('lg')
        ->variant('flat')
        ->default('14:30'),
];
