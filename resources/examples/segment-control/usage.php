<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SegmentControl;

SegmentControl::make('sm')
    ->label('Sizes · SM')
    ->size('sm')
    ->options([
        'dashboard' => 'Dashboard',
        'analytics' => 'Analytics',
        'reports' => 'Reports',
        'settings' => 'Settings',
    ]);
