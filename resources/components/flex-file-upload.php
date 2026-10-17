<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Guidance\ComponentGuidance;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;

return new ComponentDefinition(
    id: 'flex-file-upload',
    kind: ComponentKind::Field,
    styles: ['flex-file-upload'],
    stylesFrom: ['camera-surface', 'flex-text-input'],
    entry: 'flex-file-upload',
    uses: ['media-os'],
    capabilities: ['sources' => ['segment-control'], 'metadata-translations' => ['translatable-fields']],
    publications: [new PublishedResource('file-icons', 'resources/dist/assets/file-upload/icons', 'filament-flex-fields-assets/file-upload/icons')],
    guidance: new ComponentGuidance(
        summary: 'Configure file upload presentation while keeping record and storage ownership explicit.',
        renderer: FlexFileUpload::class,
        persistence: 'Disk paths can map to an attribute or application-owned related rows. A transient upload list does not automatically create arbitrary SQL relationships.',
        documentation: 'https://flex-fields.bjanczak.com/docs/file-upload-gallery-recipe',
        prerequisites: ['A configured Laravel disk and an application save/authorization policy.', 'Queued conversions require a running worker and format-capable encoder.'],
        instructions: ['Keep native disk processing and Spatie persistence as separate configured modes.',
            'Define conversions before choosing previewConversion() or fileViewerConversion().',
            'Follow completeMediaUploadsAfterSave() on the mounted field after a successful custom record commit; do not replace internal relationship hooks.',
            'Use getUploadedFileNameForStorageUsing() for a safe unique basename, with directory() controlling folders.'],
        verification: ['Save, reload and restore files and order.', 'Check a failed save, a pending conversion and independent galleries.'],
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/file-upload/usage.php', 'Upload options'),
            CodeExample::php(dirname(__DIR__).'/recipes/post-gallery/app/Filament/Pages/PostGallery.php', 'Post gallery page'),
            CodeExample::php(dirname(__DIR__).'/recipes/post-gallery/app/Support/GalleryRows.php', 'Application gallery mapper'),
            CodeExample::php(dirname(__DIR__).'/recipes/post-gallery/app/Support/GalleryFields.php', 'Reusable gallery field configuration'),
            CodeExample::php(dirname(__DIR__).'/recipes/post-gallery/app/Support/GalleryFileName.php', 'Stored filename generator'),
            CodeExample::php(dirname(__DIR__).'/recipes/post-gallery/app/Filament/Resources/PostResource.php', 'Native Resource gallery fields'),
            CodeExample::php(dirname(__DIR__).'/recipes/post-gallery/app/Filament/Resources/PostResource/Pages/CreatePost.php', 'Native Resource gallery creation'),
            CodeExample::php(dirname(__DIR__).'/recipes/post-gallery/app/Filament/Resources/PostResource/Pages/EditPost.php', 'Native Resource gallery editing'),
        ],
    ),
);
