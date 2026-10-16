<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;

MapPickerField::make('pickup')
    ->label('Pickup address')
    ->fields(['lat', 'lng', 'street', 'city', 'postcode', 'country', 'place_name'])
    ->requireStreetNumber()
    ->countries(['PL'])
    ->requiredFields(['street', 'lat', 'lng'])
    ->searchLimit(6)
    ->searchDebounce(180)
    ->defaultCenter([52.2297, 21.0122])
    ->defaultZoom(12)
    ->scrollZoom()
    ->cooperativeGestures(false)
    ->fullscreen();
