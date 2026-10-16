<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

return [
    FlexDateRangeField::make('small')
        ->label('Small · secondary')
        ->size('sm')
        ->variant('secondary')
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
    FlexDateRangeField::make('large')
        ->label('Large · flat')
        ->size('lg')
        ->variant('flat')
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
    FlexDateRangeField::make('read_only')
        ->label('Read-only range')
        ->readOnly()
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
];
