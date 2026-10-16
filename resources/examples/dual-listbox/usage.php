<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\DualListboxField;

DualListboxField::make('permissions')
    ->label('Permissions')
    ->options([
        'users.view' => [
            'label' => 'View users',
            'description' => 'Browse the user directory',
        ],
        'users.create' => [
            'label' => 'Create users',
            'description' => 'Invite and register accounts',
        ],
        'users.delete' => [
            'label' => 'Delete users',
            'description' => 'Remove accounts permanently',
        ],
        'posts.view' => [
            'label' => 'View posts',
            'description' => 'Read published content',
        ],
        'posts.edit' => [
            'label' => 'Edit posts',
            'description' => 'Update existing articles',
        ],
        'posts.publish' => [
            'label' => 'Publish posts',
            'description' => 'Make content public',
        ],
    ])
    ->variant('bordered')
    ->default([
        'users.view',
        'posts.edit',
    ]);
