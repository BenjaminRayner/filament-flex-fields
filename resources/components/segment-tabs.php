<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\SegmentTabsPlayground;

return new ComponentDefinition(
    id: 'segment-tabs',
    kind: ComponentKind::Field,
    styles: ['segment-tabs'],
    stylesFrom: ['segment-control'],
    entry: 'segment-tabs',
    ssrScripts: ['flex-fields-segment-overflow-ssr'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.segment-tabs.label',
        description: 'filament-flex-fields::catalog.components.segment-tabs.description',
        category: CatalogCategory::Navigation,
        keywords: ['segment', 'tabs'],
        icon: 'hugeicons-archive-02',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/segment-tabs/usage.php'),
        ],
        demoClass: SegmentTabsPlayground::class,
        demoSlug: 'segment-tabs',
        sort: 100,
        documentation: 'segmenttabs.md',
    ),
);
