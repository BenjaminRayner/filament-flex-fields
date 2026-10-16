<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;

SignatureField::make('contract')
    ->label('Sign here')
    ->required();
