<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Filament\Notifications\Notification;

FlexFileUpload::make('attachments')
    ->withRecommendedDefaults()
    ->multiple()
    ->maxFiles(5)
    ->uploadLayout('grid') // Or 'list': header density is independent.
    ->compactHeader()
    ->fileClickAction(function (string $fileName): void {
        Notification::make()->title('Selected: '.$fileName)->success()->send();
    });

// Application routes/policies remain yours. The callback receives server-owned data.
FlexFileUpload::make('documents')
    ->withRecommendedDefaults()
    ->multiple()
    ->documentsOnly()
    ->compactHeader()
    ->fileClickUrl(fn (string $fileKey, string $mimeType): ?string => $mimeType === 'application/pdf'
        ? route('documents.preview', ['upload' => $fileKey])
        : null);

// Smaller tiles, native action menu, tile click opens the image viewer.
FlexImageUpload::make('photos')->multiple()->uploadLayout('grid')
    ->gridSize('sm')->gridActions('menu')->fileClickPreview()
    ->previewConversion('thumb')->fileViewerConversion('large')
    ->imageEditor()->reorderable();
