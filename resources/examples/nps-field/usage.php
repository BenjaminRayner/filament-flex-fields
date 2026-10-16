<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NpsField;

NpsField::make('nps_standard')
    ->label('Standard NPS (0-10)')
    ->options([
        0,
        1,
        2,
        3,
        4,
        5,
    ])
    ->variant('pills');
