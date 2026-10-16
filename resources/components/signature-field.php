<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\SignatureFieldPlayground;

return new ComponentDefinition(
    id: 'signature-field',
    kind: ComponentKind::Field,
    styles: ['signature-field'],
    stylesFrom: [],
    entry: 'signature-field',
    uses: ['media-os'],
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.signature-field.label',
        description: 'filament-flex-fields::catalog.components.signature-field.description',
        category: CatalogCategory::Media,
        keywords: ['signature', 'field'],
        icon: 'hugeicons-signature',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/signature-field/usage.php'),
        ],
        demoClass: SignatureFieldPlayground::class,
        demoSlug: 'signature-field',
        sort: 360,
        documentation: 'signaturefield.md',
    ),
);
