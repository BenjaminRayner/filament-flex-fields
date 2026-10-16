<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ColorSwatchField;

ColorSwatchField::make('sm')
    ->label('Small')
    ->size('sm')
    ->sectionLabel('Predefined')
    ->tooltips([
        'charcoal' => 'Charcoal',
        'magenta' => 'Magenta',
        'blue' => 'Royal Blue',
        'green' => 'Emerald',
        'yellow' => 'Golden Yellow',
        'white' => 'White',
    ])
    ->colors([
        'charcoal' => '#18181b',
        'magenta' => '#d946ef',
        'blue' => '#3b82f6',
        'green' => '#22c55e',
        'yellow' => '#eab308',
        'white' => '#ffffff',
    ])
    ->default('green');

// High-contrast selection in both themes. The database stores "white" or "black".
ColorSwatchField::make('theme_accent')
    ->colors(['white' => '#fff', 'black' => '#000'])
    ->tooltips(['white' => __('White'), 'black' => __('Black')])
    ->default('white')
    ->required();

// Dynamic palette, compact presentation and a custom section icon.
ColorSwatchField::make('brand_accent')
    ->colors(fn (): array => ['violet' => '#7c3aed', 'blue' => '#2563eb'])
    ->size('sm')
    ->sectionLabel(__('Brand'))
    ->sectionIcon('hugeicons-swatch-book')
    ->tooltips();

ColorSwatchField::make('locked_accent')
    ->colors(['white' => '#fff', 'black' => '#000'])
    ->default('black')
    ->disabled();
