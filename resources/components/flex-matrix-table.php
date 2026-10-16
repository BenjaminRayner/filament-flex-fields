<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexMatrixTablePlayground;

return new ComponentDefinition(
    id: 'flex-matrix-table', kind: ComponentKind::Field,
    stylesFrom: ['matrix-choice-field'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.flex-matrix-table.label',
        description: 'filament-flex-fields::catalog.components.flex-matrix-table.description',
        category: CatalogCategory::Collections,
        keywords: ['flex', 'matrix', 'table', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-grid-table',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/flex-matrix-table/usage.php'),
        ],
        demoClass: FlexMatrixTablePlayground::class,
        demoSlug: 'flex-matrix-table',
        sort: 393,
        documentation: 'flex-matrix-table.md',
    ),
);
