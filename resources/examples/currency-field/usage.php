<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;

CurrencyField::make('pln')
    ->label('Amount (PLN)')
    ->variant('primary')
    ->required();
