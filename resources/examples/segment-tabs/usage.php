<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;

SegmentTabs::make('Account')
    ->tabs([
        SegmentTab::make('General')
            ->icon(FlexIcon::Person)
            ->schema([
                FlexTextInput::make('name')->label('Name'),
                FlexTextInput::make('email')->label('Email'),
            ]),
        SegmentTab::make('Advanced')
            ->icon(FlexIcon::Gear)
            ->schema([
                FlexTextInput::make('api_key')->label('API key'),
                FlexTextInput::make('webhook')->label('Webhook URL'),
            ]),
    ]);

SegmentTabs::make('Profile')
    ->variant('ghost')
    ->separators(false)
    ->fullWidth()
    ->tabs([
        SegmentTab::make('Details')
            ->schema([
                FlexTextInput::make('display_name')->label('Display name'),
            ]),
        SegmentTab::make('Bio')
            ->schema([
                FlexTextInput::make('bio')->label('Short bio'),
            ]),
    ]);
