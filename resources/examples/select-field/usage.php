<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('status')
    ->label('Status')
    ->options([
        'draft' => 'Draft',
        'published' => 'Published',
    ])
    ->searchable()
    ->placeholder('Choose status');
