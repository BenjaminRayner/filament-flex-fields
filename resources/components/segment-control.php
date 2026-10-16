<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\SegmentControlPlayground;

return new ComponentDefinition(
    id: 'segment-control',
    kind: ComponentKind::Field,
    styles: ['segment-control'],
    stylesFrom: [],
    entry: 'segment-control',
    ssrScripts: ['flex-fields-segment-overflow-ssr'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.segment-control.label',
        description: 'filament-flex-fields::catalog.components.segment-control.description',
        category: CatalogCategory::Controls,
        keywords: ['segment', 'control'],
        icon: 'hugeicons-carousel-horizontal-02',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/segment-control/usage.php'),
        ],
        demoClass: SegmentControlPlayground::class,
        demoSlug: 'segment-control',
        sort: 90,
        documentation: 'segmentcontrol.md',
    ),
);
