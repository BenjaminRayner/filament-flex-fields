<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

return [
    FlexDateRangeField::make('range')
        ->label('Built-in reporting periods')
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15'])
        ->helperText('Today, Yesterday, Last 7 days and the other built-in periods.'),
    FlexDateRangeField::make('no_presets')
        ->label('No shortcuts')
        ->rangePresets([])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15'])
        ->helperText('The left panel is completely removed.'),
    FlexDateRangeField::make('custom_presets')
        ->label('Custom periods')
        ->rangePresets([
            ['label' => 'Autumn campaign', 'start' => '2026-10-01', 'end' => '2026-10-31'],
            ['label' => 'Launch week', 'start' => '2026-10-12', 'end' => '2026-10-18'],
        ])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15'])
        ->helperText('Only Autumn campaign and Launch week appear.'),
];
