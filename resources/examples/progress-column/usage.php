<?php

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ProgressColumn;

// Add to your Filament table's columns. State comes from the record attribute.
ProgressColumn::make('completion')
    ->progressSize('sm')->progressColor('success')->showValue();
