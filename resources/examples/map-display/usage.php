<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapDisplayField;

MapDisplayField::make('location')
    ->mapProvider('maplibre')
    ->mapFeatures($geoJson)
    ->fitFeatures()
    ->showLegend()
    ->scrollZoom()
    ->cooperativeGestures(false)
    ->fullscreen();
