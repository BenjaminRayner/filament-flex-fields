<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('tech')
    ->options($options)
    ->searchable()
    ->inlineSearch()
    ->clearable()
    ->prefixIcon('hugeicons-search-01')
    ->dropdownAlign('end');
