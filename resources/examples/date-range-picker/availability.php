<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

return [
    FlexDateRangeField::make('availability')
        ->label('Working-day endpoints')
        ->disabledWeekdays([0, 6])
        ->disabledDates(['2026-10-16'])
        ->rangePresets([])
        ->default(['start' => '2026-10-12', 'end' => '2026-10-15'])
        ->helperText('Weekend endpoints and October 16 are blocked.'),
    FlexDateRangeField::make('same_day')
        ->label('Same day allowed')
        ->allowSameDay()
        ->rangePresets([])
        ->default(['start' => '2026-10-15', 'end' => '2026-10-15']),
    FlexDateRangeField::make('dynamic_presets')
        ->label('Dynamic shortcuts')
        ->rangePresets(static fn (): array => [
            [
                'label' => 'Current month',
                'start' => now()->startOfMonth()->format('Y-m-d'),
                'end' => now()->endOfMonth()->format('Y-m-d'),
            ],
        ])
        ->default(['start' => '2026-10-10', 'end' => '2026-10-15'])
        ->helperText('The PHP callback resolves this month when the field is built.'),
];
