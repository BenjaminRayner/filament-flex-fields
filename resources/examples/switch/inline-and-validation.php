<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;

// Bare switch; label remains its accessible name.
SwitchField::make('enabled')
    ->label('Enabled')
    ->inline();

// Switch beside the form label, helper text below.
SwitchField::make('notifications')
    ->label('Notifications')
    ->inlineWithLabel()
    ->helperText('Receive updates about your account.');

// Label before the inline switch.
SwitchField::make('dark_mode')
    ->label('Dark mode')
    ->inlineWithLabel()
    ->labelPosition('end');

// Accepted means true; declined means false.
SwitchField::make('terms')->label('I accept the terms')->accepted();
SwitchField::make('restricted')->label('Restricted')->declined();
SwitchField::make('locked')->label('Managed setting')->default(true)->disabled();

// Deferred binding is the default. Use live() for a server change callback.
SwitchField::make('email')
    ->label('Email notifications')
    ->live()
    ->afterStateUpdated(function (bool $state): void {
        // React to this change; validate the form before persisting it.
    });
