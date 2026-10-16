<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\SignaturePreviewColumnPlayground;

return new ComponentDefinition(
    id: 'signature-preview-column',
    kind: ComponentKind::Column,
    styles: ['signature-preview-column'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'Signature preview column',
        description: 'Read-only table presentation with configurable appearance and empty states.',
        category: CatalogCategory::DataDisplay,
        keywords: ['table', 'column', 'signature-preview-column'],
        icon: 'hugeicons-signature',
        author: 'FLEX',
        official: true,
        examples: [CodeExample::php(dirname(__DIR__).'/examples/signature-preview-column/usage.php')],
        demoClass: SignaturePreviewColumnPlayground::class,
        demoSlug: 'signature-preview-column',
        groupedWith: 'signature-field',
        documentation: 'signaturefield.md',
    ),
);
