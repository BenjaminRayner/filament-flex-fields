<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\TrafficSplitPlayground;

return new ComponentDefinition(
    id: 'traffic-split',
    kind: ComponentKind::Field,
    styles: ['traffic-split'],
    stylesFrom: [],
    entry: 'traffic-split',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.traffic-split.label',
        description: 'filament-flex-fields::catalog.components.traffic-split.description',
        category: CatalogCategory::Controls,
        keywords: ['traffic', 'split'],
        icon: 'hugeicons-segment',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/traffic-split/usage.php'),
        ],
        demoClass: TrafficSplitPlayground::class,
        demoSlug: 'traffic-split',
        sort: 140,
        documentation: 'trafficsplit.md',
    ),
);
