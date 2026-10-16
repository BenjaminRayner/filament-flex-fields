<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ProgressBarPlayground;

return new ComponentDefinition(
    id: 'progress-bar',
    kind: ComponentKind::Display,
    styles: ['progress-bar'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.progress-bar.label',
        description: 'filament-flex-fields::catalog.components.progress-bar.description',
        category: CatalogCategory::DataDisplay,
        keywords: ['progress', 'bar', 'table', 'column', 'display', 'tabela'],
        icon: 'hugeicons-bar-chart',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/progress-bar/usage.php'),
        ],
        demoClass: ProgressBarPlayground::class,
        demoSlug: 'progress-bar',
        sort: 420,
        documentation: 'progressbar.md',
    ),
);
