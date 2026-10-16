<?php

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\SignaturePreviewColumn;

// Add to your Filament table's columns. State comes from the record attribute.
SignaturePreviewColumn::make('signature')
    ->previewSize('md');
