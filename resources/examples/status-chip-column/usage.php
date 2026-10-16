<?php

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\StatusChipColumn;

// Add to your Filament table's columns. State comes from the record attribute.
StatusChipColumn::make('status')
    ->chipSize('md')->chipColor('warning');
