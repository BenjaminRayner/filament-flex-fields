<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Guidance\ComponentGuidance;
use Bjanczak\FilamentFlexFields\Support\Playground\FontSelectPlayground;

return new ComponentDefinition(
    id: 'font-select',
    kind: ComponentKind::Field,
    styles: ['font-select'],
    uses: ['select-field'],
    entry: 'font-select',
    guidance: new ComponentGuidance(
        summary: 'Select a catalog font with virtualized, ownership-aware previews.',
        renderer: FontSelect::class,
        persistence: 'Store a provider-prefixed string ID such as google:inter. Preview fonts are not full content font installations.',
        documentation: 'https://flex-fields.bjanczak.com/docs/font-select',
        instructions: ['Use previewSelectedFont(false) for the regular input font with dropdown-only specimens.',
            'Use categories(), subsets() and fonts() for the current server-validated catalog policy. Do not save a label or CSS URL.',
            'Load full website or panel fonts through the application font pipeline; never remove external font ownership.'],
        verification: ['Save and reopen the same font ID.', 'Check rapid query changes, scroll and modal close/reopen.'],
    ),
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.font-select.label',
        description: 'filament-flex-fields::catalog.components.font-select.description',
        category: CatalogCategory::Pickers,
        keywords: ['font', 'typography', 'google fonts', 'preview', 'virtualization'],
        icon: 'hugeicons-type-outline',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/font-select/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/font-select/application.php'),
        ],
        demoClass: FontSelectPlayground::class,
        demoSlug: 'font-select',
        sort: 32,
        documentation: 'font-select.md',
    ),
);
