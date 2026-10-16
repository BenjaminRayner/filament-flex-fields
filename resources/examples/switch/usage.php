<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;

// Bordered row. The whole row is clickable.
SwitchField::make('animations')
    ->label('Animations')
    ->default(true);

// Filled row.
SwitchField::make('notifications')
    ->label('Email notifications')
    ->variant('secondary');

// Card, description and badge.
SwitchField::make('new_sidebar')
    ->label('Try the new sidebar')
    ->description('Keep your pages and meetings within reach.')
    ->badge('New')
    ->badgeColor('primary')
    ->layout('card');
