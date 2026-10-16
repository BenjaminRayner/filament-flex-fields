<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TrafficSplit;

TrafficSplit::make('two')
    ->label('Two segments')
    ->segmentCount(2)
    ->default([
        50,
        50,
    ]);
