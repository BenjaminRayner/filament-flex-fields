<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;

MapPickerField::make('location')
    ->fields(['lat', 'lng', 'street', 'city', 'postcode', 'country', 'country_name'])
    ->streetAddressesOnly()
    ->live()
    ->afterStateUpdated(function (?array $state, Set $set): void {
        $set('street', data_get($state, 'street', ''));
        $set('city', data_get($state, 'city', ''));
        $set('postcode', data_get($state, 'postcode', ''));
        $set('country', data_get($state, 'country', ''));
        $set('country_name', data_get($state, 'country_name', ''));
        $set('latitude', (string) data_get($state, 'lat', ''));
        $set('longitude', (string) data_get($state, 'lng', ''));
    });

Grid::make(3)->schema([
    FlexTextInput::make('street')->disabled()->dehydrated(false),
    FlexTextInput::make('city')->disabled()->dehydrated(false),
    FlexTextInput::make('postcode')->disabled()->dehydrated(false),
    FlexTextInput::make('country')->disabled()->dehydrated(false),
    FlexTextInput::make('country_name')->label('Country name')->disabled()->dehydrated(false),
    FlexTextInput::make('latitude')->disabled()->dehydrated(false),
    FlexTextInput::make('longitude')->disabled()->dehydrated(false),
]);
