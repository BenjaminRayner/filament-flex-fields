<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexTimeField::make('rtl')
        ->label('Arabic · RTL')
        ->locale('ar')
        ->hourCycle(12)
        ->hideTimeZone()
        ->default('14:30'),
    FlexTimeField::make('read_only')
        ->label('Read-only time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->readOnly()
        ->default('14:30'),
    FlexTimeField::make('disabled')
        ->label('Disabled time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->disabled()
        ->default('14:30'),
];
