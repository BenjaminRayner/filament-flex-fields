<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCheckboxCards;

ChoiceCheckboxCards::make('stack_checkbox')
    ->label('Stack · checkbox indicator')
    ->options([
        'security' => [
            'label' => 'Security',
            'description' => 'Real-time threat detection and prevention',
        ],
        'storage' => [
            'label' => 'Storage',
            'description' => 'Unlimited cloud storage for your files',
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Advanced reporting and insights',
        ],
    ])
    ->layout('stack')
    ->indicator('checkbox');
