<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('tag')
    ->options($existingTags)
    ->searchable()
    ->allowCreateOption()
    ->recentOptions(['laravel', 'filament'])
    ->suggestedOptions(['tailwindcss']);
