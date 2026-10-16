<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('theme')
    ->size('sm')
    ->variant('secondary');

SelectField::make('row')
    ->variant('item-card');
