<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

UserSelect::make('single')
    ->label('Assignee')
    ->options([
        'jane' => [
            'label' => 'Jane Cooper',
            'description' => 'jane.cooper@example.com',
            'image' => 'https://ui-avatars.com/api/?name=Jane+Cooper&background=3b82f6&color=fff&size=128',
            'verified' => true,
        ],
        'alex' => [
            'label' => 'Alex Rivera',
            'description' => 'alex.rivera@example.com',
            'verified' => false,
        ],
        'sam' => [
            'label' => 'Sam Chen',
            'description' => 'sam.chen@example.com',
            'image' => 'https://ui-avatars.com/api/?name=Sam+Chen&background=10b981&color=fff&size=128',
            'verified' => true,
        ],
        'morgan' => [
            'label' => 'Morgan Lee',
            'description' => 'morgan.lee@example.com',
            'verified' => true,
        ],
        'riley' => [
            'label' => 'Riley Brooks',
            'description' => 'riley.brooks@example.com',
            'image' => 'https://ui-avatars.com/api/?name=Riley+Brooks&background=f59e0b&color=fff&size=128',
            'verified' => false,
        ],
        'casey' => [
            'label' => 'Casey Jordan',
            'description' => 'casey.jordan@example.com',
            'verified' => true,
        ],
    ])
    ->variant('bordered');
