<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

// The model stores photos as a JSON array of disk paths (array cast).
FlexImageUpload::make('photos')
    ->multiple()->disk('public')->directory('posts/photos')->visibility('public')
    ->imagesOnly()->maxSize(5120)->maxFiles(12)->uploadLayout('grid')
    ->preventFilePathTampering()->fileViewer();

// Generate your own unique storage name while keeping original names for display.
FlexFileUpload::make('documents')
    ->multiple()->disk('local')->directory('documents')
    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => 'invoice-'.Str::uuid().'.'.$file->extension())
    ->storeFileNamesIn('document_names');

// Custom gallery-table persistence and legacy thumbnail recipes:
// See the Models and galleries page in the File Upload documentation.
