<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapZonesField;

MapZonesField::make('location')
    ->mapProvider('maplibre')
    ->drawingTools(['polygon', 'rectangle', 'circle'])
    ->maxFeatures(20)
    ->maxZoneVertices(128)
    ->snapDistance(12)
    ->preventZoneOverlap()
    ->allowZoneTouching()
    ->requireZoneLabels()
    ->showLegend()
    ->scrollZoom()
    ->cooperativeGestures(false)
    ->fullscreen();
