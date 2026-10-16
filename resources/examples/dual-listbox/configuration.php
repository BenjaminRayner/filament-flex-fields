<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\DualListboxField;

// Selection limits are validated on the server.
DualListboxField::make('statuses')
    ->options([
        'draft' => 'Draft',
        'reviewing' => 'Reviewing',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
    ->required()
    ->minItems(1)
    ->maxItems(3)
    ->size('md')
    ->variant('bordered');

// Descriptions and per-option interaction locks.
DualListboxField::make('permissions')
    ->options([
        'read' => ['label' => 'Read', 'description' => 'Browse records'],
        'write' => ['label' => 'Write', 'description' => 'Update records'],
        'delete' => ['label' => 'Delete', 'disabled' => true],
    ])
    ->default(['read'])
    ->disabledOptions(['read'])
    ->availableLabel('Available permissions')
    ->selectedLabel('Granted permissions')
    ->listHeight('20rem')
    ->virtualThreshold(100);

// A minimal interface with independent options.
DualListboxField::make('categories')
    ->options(['news' => 'News', 'events' => 'Events'])
    ->searchable(false)
    ->showTransferButtons(false)
    ->moveOnDoubleClick()
    ->reorderable(false);

// Keep the selected order visible without editing it.
DualListboxField::make('approved_statuses')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->readOnly();
