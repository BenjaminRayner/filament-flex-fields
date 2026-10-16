<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

return [
    FlexTimeField::make('typed')
        ->label('Guided time entry')
        ->hourCycle(24)
        ->hideTimeZone()
        ->editable()
        ->default('14:30')
        ->helperText('Numeric time segments; ArrowUp/ArrowDown adjust the selected segment.'),
    FlexTimeField::make('optional')
        ->label('Clearable time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->clearable()
        ->default('14:30')
        ->helperText('Use × to clear this optional value.'),
    FlexTimeField::make('zone')
        ->label('Clock with timezone label')
        ->hourCycle(12)
        ->timeZone('Europe/Warsaw')
        ->default('14:30')
        ->helperText('The clock remains a local HH:mm value.'),
];
