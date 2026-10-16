<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexFileUploadPlayground;

return new ComponentDefinition(
    id: 'file-upload', kind: ComponentKind::Field,
    uses: ['flex-file-upload'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.file-upload.label',
        description: 'filament-flex-fields::catalog.components.file-upload.description',
        category: CatalogCategory::Media,
        keywords: ['file', 'upload'],
        icon: 'hugeicons-cloud-upload',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/file-upload/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/file-upload/interaction.php'),
            CodeExample::php(dirname(__DIR__).'/examples/file-upload/persistence.php'),
        ],
        demoClass: FlexFileUploadPlayground::class,
        demoSlug: 'file-upload',
        sort: 310,
        documentation: 'flexfileupload-and-fleximageupload.md',
        popularRank: 4,
    ),
);
