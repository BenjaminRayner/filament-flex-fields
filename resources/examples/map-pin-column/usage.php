<?php

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\MapPinColumn;

// Add to your Filament table's columns. State comes from the record attribute.
MapPinColumn::make('location')
    ->pinSize('sm')->showLabel();
