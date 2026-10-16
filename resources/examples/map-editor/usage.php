<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapEditorField;

MapEditorField::make('location')
    ->mapProvider('maplibre')
    ->drawingTools(['point', 'polyline', 'polygon', 'rectangle', 'circle'])
    ->maxFeatures(40)
    ->geometryStrokeWidth(2)
    ->snapDistance(12)
    ->scrollZoom()
    ->cooperativeGestures(false)
    ->fullscreen();
