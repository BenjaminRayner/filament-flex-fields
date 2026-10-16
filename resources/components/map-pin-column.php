<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\MapPinColumnPlayground;

return new ComponentDefinition(
    id: 'map-pin-column',
    kind: ComponentKind::Column,
    styles: ['map-pin-column'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'Map pin column',
        description: 'Read-only table presentation with configurable appearance and empty states.',
        category: CatalogCategory::DataDisplay,
        keywords: ['table', 'column', 'map-pin-column'],
        icon: 'hugeicons-maps-location-02',
        author: 'FLEX',
        official: true,
        examples: [CodeExample::php(dirname(__DIR__).'/examples/map-pin-column/usage.php')],
        demoClass: MapPinColumnPlayground::class,
        demoSlug: 'map-pin-column',
        groupedWith: 'map-picker',
        documentation: 'mappickerfield.md',
    ),
);
