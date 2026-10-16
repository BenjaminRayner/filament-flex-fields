<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;

CountryField::make('basic')
    ->label('Country')
    ->variant('primary')
    ->required();

CountryField::make('country_with_kosovo')
    ->additionalRegions(['XK'])
    ->showCountryCode();

CountryField::make('french_country')
    ->locale('fr')
    ->countries(['PL', 'US', 'DE']);
