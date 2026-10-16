<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ItemCard;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ItemCardGroup;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;

// Grouped navigation with one shared surface and pressable rows.
ItemCardGroup::make('Account')
    ->key('account-navigation', isInheritable: false)
    ->description('Manage your profile and security')
    ->variant('secondary')
    ->separated()
    ->schema([
        ItemCard::make('Profile')
            ->description('Update your personal information')
            ->icon(FlexIcon::Person)
            ->url('/account/profile')
            ->chevron(),
        ItemCard::make('Security')
            ->description('Manage your password and two-factor authentication')
            ->icon(FlexIcon::Key)
            ->url('/account/security')
            ->chevron(),
    ]);

// Real shared fields: each field keeps its own configuration and validation.
ItemCardGroup::make('Preferences')
    ->key('preference-controls', isInheritable: false)
    ->headerStyle('outside')
    ->variant('tertiary')
    ->schema([
        ItemCard::make('Language')
            ->icon(FlexIcon::Globe)
            ->schema([
                SelectField::make('language')
                    ->options(['en' => 'English', 'pl' => 'Polish'])
                    ->variant('item-card')
                    ->required()
                    ->hiddenLabel(),
            ]),
        ItemCard::make('Notifications')
            ->icon(FlexIcon::Bell)
            ->schema([
                SwitchField::make('notifications_enabled')
                    ->size('sm')
                    ->inline()
                    ->hiddenLabel(),
            ]),
    ]);
