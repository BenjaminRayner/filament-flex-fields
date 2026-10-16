<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMatrixTable;

FlexMatrixTable::make('basic')
    ->label('Car Configuration')
    ->default([
        'a8437022-16c8-40e4-ba16-48d0c5c14d55' => [],
    ]);
