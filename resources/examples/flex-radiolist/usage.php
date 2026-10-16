<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexRadiolist;

FlexRadiolist::make('delivery')
    ->label('Delivery method')
    ->options([
        'standard' => [
            'label' => 'Standard',
            'description' => '4–10 business days',
            'icon' => 'hugeicons-shopping-cart-01',
        ],
        'express' => [
            'label' => 'Express',
            'description' => '2–5 business days',
            'icon' => 'hugeicons-flash',
        ],
        'super_fast' => [
            'label' => 'Super Fast',
            'description' => '1 business day',
            'icon' => 'hugeicons-rocket-01',
        ],
        'archived' => [
            'label' => 'Archived',
            'description' => 'No longer available',
            'icon' => 'hugeicons-archive',
            'disabled' => true,
        ],
    ]);

// Compact labels retain the same animated radio indicator.
FlexRadiolist::make('file')
    ->variant('label-only')
    ->options(['proposal' => 'Project proposal.pdf', 'report' => 'Financial report.xlsx'])
    ->default('proposal')
    ->required();

// Optional helper arrays, size and panel accent.
FlexRadiolist::make('service')
    ->options(['standard' => 'Standard', 'express' => 'Express', 'retired' => 'Retired'])
    ->descriptions(['standard' => '4–10 business days', 'express' => '2–5 business days'])
    ->icons(['standard' => 'hugeicons-shopping-cart-01', 'express' => 'hugeicons-flash'])
    ->disabledOptions(['retired'])
    ->size('lg')
    ->color('success');
