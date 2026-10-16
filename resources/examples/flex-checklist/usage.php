<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexChecklist;

FlexChecklist::make('documents')
    ->label('Project files')
    ->options([
        'documents' => [
            'label' => 'Documents',
            'description' => 'Shared project folder',
            'icon' => 'hugeicons-folder-01',
        ],
        'budget' => [
            'label' => 'Budget.xlsx',
            'description' => 'Financial spreadsheet',
            'icon' => 'hugeicons-grid-view',
        ],
        'reports' => [
            'label' => 'Reports.pdf',
            'description' => 'Monthly performance report',
            'icon' => 'hugeicons-file-01',
        ],
        'archived' => [
            'label' => 'Archived',
            'description' => 'Read-only archive',
            'icon' => 'hugeicons-archive',
            'disabled' => true,
        ],
        'old_backup' => [
            'label' => 'Old backup.zip',
            'description' => 'Legacy backup — cannot be selected',
            'icon' => 'hugeicons-server-stack-01',
            'disabled' => true,
        ],
    ]);

// Plain labels; three control sizes are available: sm, md and lg.
FlexChecklist::make('features')
    ->options(['wifi' => 'Wi-Fi', 'parking' => 'Parking', 'breakfast' => 'Breakfast'])
    ->size('sm')
    ->color('success')
    ->default(['wifi']);

// The limit applies immediately to additions; minimum is validated on save.
FlexChecklist::make('services')
    ->options(['cleaning' => 'Cleaning', 'catering' => 'Catering', 'transport' => 'Transport'])
    ->minSelections(1)
    ->maxSelections(2)
    ->required();

// Require exactly two selections.
FlexChecklist::make('finalists')
    ->options(['a' => 'Candidate A', 'b' => 'Candidate B', 'c' => 'Candidate C'])
    ->exactSelections(2);
