<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCards;

ChoiceCards::make('plans')
    ->label('Select a plan')
    ->options([
        'starter' => [
            'label' => 'Starter',
            'description' => 'For individuals and small projects',
            'price' => '$5',
            'price_suffix' => '/mo',
        ],
        'pro' => [
            'label' => 'Pro',
            'description' => 'For growing teams and businesses',
            'price' => '$15',
            'price_suffix' => '/mo',
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'description' => 'For large organizations at scale',
            'price' => '$45',
            'price_suffix' => '/mo',
        ],
    ])
    ->layout('stack')
    ->indicator('check')
    ->default('pro');
