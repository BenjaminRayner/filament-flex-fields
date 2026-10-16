<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ProgressColumnPlayground;

return new ComponentDefinition(
    id: 'progress-column',
    kind: ComponentKind::Column,
    styles: ['progress-column'],
    stylesFrom: ['progress-bar'],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'Progress column',
        description: 'Read-only table presentation with configurable appearance and empty states.',
        category: CatalogCategory::DataDisplay,
        keywords: ['table', 'column', 'progress-column'],
        icon: 'hugeicons-bar-chart',
        author: 'FLEX',
        official: true,
        examples: [CodeExample::php(dirname(__DIR__).'/examples/progress-column/usage.php')],
        demoClass: ProgressColumnPlayground::class,
        demoSlug: 'progress-column',
        groupedWith: 'progress-bar',
        documentation: 'progressbar.md',
    ),
);
