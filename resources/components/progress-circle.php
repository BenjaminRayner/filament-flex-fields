<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ProgressCirclePlayground;

return new ComponentDefinition(
    id: 'progress-circle',
    kind: ComponentKind::Display,
    styles: ['progress-circle'],
    stylesFrom: [],
    entry: null,
    capabilities: ['interactive-breakdown' => ['progress-circle-breakdown']],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.progress-circle.label',
        description: 'filament-flex-fields::catalog.components.progress-circle.description',
        category: CatalogCategory::DataDisplay,
        keywords: ['progress', 'circle', 'table', 'column', 'display', 'tabela'],
        icon: 'hugeicons-progress-04',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/progress-circle/usage.php'),
        ],
        demoClass: ProgressCirclePlayground::class,
        demoSlug: 'progress-circle',
        sort: 430,
        documentation: 'progresscircle.md',
    ),
);
