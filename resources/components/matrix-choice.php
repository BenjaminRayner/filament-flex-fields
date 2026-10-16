<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\MatrixChoiceFieldPlayground;

return new ComponentDefinition(
    id: 'matrix-choice', kind: ComponentKind::Field,
    uses: ['matrix-choice-field'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.matrix-choice.label',
        description: 'filament-flex-fields::catalog.components.matrix-choice.description',
        category: CatalogCategory::Collections,
        keywords: ['matrix', 'choice', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-table-properties',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/matrix-choice/usage.php'),
        ],
        demoClass: MatrixChoiceFieldPlayground::class,
        demoSlug: 'matrix-choice',
        sort: 395,
        documentation: 'matrixchoicefield.md',
    ),
);
