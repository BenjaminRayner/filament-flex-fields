<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapDisplayPlayground;

return new ComponentDefinition(
    id: 'map-display', kind: ComponentKind::Field, uses: ['map-picker'],
    catalog: new ComponentPresentation(
        listed: false,
        label: ['en' => 'Map display', 'pl' => 'Widok mapy'],
        description: ['en' => 'Display supplied layers with tooltips and legends.', 'pl' => 'Wyświetlaj warstwy z opisami i legendą.'],
        category: CatalogCategory::Location,
        keywords: ['map', 'location', 'map-display'], icon: 'hugeicons-maps-global-01',
        author: 'FLEX', official: true, demoClass: MapDisplayPlayground::class,
        demoSlug: 'map-display', sort: 346,
        examples: [CodeExample::php(dirname(__DIR__).'/examples/map-display/usage.php')],
    ),
);
