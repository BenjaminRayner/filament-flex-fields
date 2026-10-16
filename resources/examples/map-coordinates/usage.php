<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapCoordinatesField;

MapCoordinatesField::make('location')
    ->mapProvider('maplibre')
    ->defaultCenter([52.2297, 21.0122])
    ->geolocation()
    ->scrollZoom()
    ->cooperativeGestures(false)
    ->fullscreen();
