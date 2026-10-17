<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Guidance\ComponentGuidance;
use Bjanczak\FilamentFlexFields\Support\Playground\SignatureFieldPlayground;

return new ComponentDefinition(
    id: 'signature-field',
    kind: ComponentKind::Field,
    styles: ['signature-field'],
    stylesFrom: [],
    entry: 'signature-field',
    uses: ['media-os'],
    capabilities: [],
    guidance: new ComponentGuidance(
        summary: 'Capture and restore a signature in an existing Filament form.',
        renderer: SignatureField::class,
        persistence: 'The default mode stores SVG text in a text attribute, not an array cast. Media-backed modes require their own documented lifecycle.',
        instructions: ['Preserve application authorization and save only validated form state.',
            'A drawn signature alone does not establish identity or legal consent.'],
        verification: ['Reject an empty required signature.', 'Draw, save, reload, edit and save again.'],
    ),
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
