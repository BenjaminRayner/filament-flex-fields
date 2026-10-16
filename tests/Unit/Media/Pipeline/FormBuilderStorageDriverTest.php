<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadLayout;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Handlers\MediaFieldTypeHandler;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

it('builds FlexSpatieMediaLibraryFileUpload when storage_driver is spatie', function () {
    if (! class_exists(SpatieMediaLibraryFileUpload::class)) {
        $this->markTestSkipped('Spatie Media Library plugin not installed.');
    }

    $handler = new MediaFieldTypeHandler;
    $field = $handler->make(new FlexFieldDefinition(
        slug: 'contract',
        label: 'Contract',
        type: FieldType::File,
        config: [
            'storage_driver' => 'spatie',
            'media_collection' => 'contracts',
        ],
    ), 'contract');

    expect($field)->toBeInstanceOf(FlexSpatieMediaLibraryFileUpload::class);
});

it('builds FlexFileUpload when storage_driver is disk', function () {
    $handler = new MediaFieldTypeHandler;
    $field = $handler->make(new FlexFieldDefinition(
        slug: 'contract',
        label: 'Contract',
        type: FieldType::File,
        config: [
            'storage_driver' => 'disk',
            'use_spatie_media_library' => true,
        ],
    ), 'contract');

    expect($field)->toBeInstanceOf(FlexFileUpload::class);
});

it('applies presentation settings through the public schema configuration', function (): void {
    $field = (new MediaFieldTypeHandler)->make(new FlexFieldDefinition(
        slug: 'gallery', label: 'Gallery', type: FieldType::Image,
        config: ['upload_layout' => 'grid', 'grid_columns' => 4, 'avatar_size' => 'sm'],
    ), 'gallery');

    expect($field)->toBeInstanceOf(FlexFileUpload::class);
    $presentation = $field->getUploadPresentation();
    expect($presentation->layout)->toBe(UploadLayout::Grid)
        ->and($presentation->gridColumns)->toBe(4)
        ->and($presentation->avatarSize)->toBe(96);
});

it('honors deprecated use_spatie_media_library alias when storage_driver omitted', function () {
    if (! class_exists(SpatieMediaLibraryFileUpload::class)) {
        $this->markTestSkipped('Spatie Media Library plugin not installed.');
    }

    $handler = new MediaFieldTypeHandler;
    $field = $handler->make(new FlexFieldDefinition(
        slug: 'contract',
        label: 'Contract',
        type: FieldType::File,
        config: [
            'use_spatie_media_library' => true,
            'media_collection' => 'contracts',
        ],
    ), 'contract');

    expect($field)->toBeInstanceOf(FlexSpatieMediaLibraryFileUpload::class);
});
