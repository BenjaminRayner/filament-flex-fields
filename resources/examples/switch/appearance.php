<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;

// Three independent sizes.
SwitchField::make('small')->label('Small')->size('sm');
SwitchField::make('medium')->label('Medium')->size('md');
SwitchField::make('large')->label('Large')->size('lg');

// Switch before the label, compact row and optional ripple.
SwitchField::make('compact')
    ->label('Compact setting')
    ->labelPosition('end')
    ->compact()
    ->ripple();

// Distinct colors and Hugeicons for enabled/disabled states.
SwitchField::make('sound')
    ->label('Sound')
    ->onColor('success')
    ->offColor('danger')
    ->onIcon('hugeicons-volume-high')
    ->offIcon('hugeicons-volume-off');
