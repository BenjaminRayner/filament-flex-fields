<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapMarkersPlayground;

return new ComponentDefinition(
    id: 'map-markers', kind: ComponentKind::Field, uses: ['map-picker'],
    catalog: new ComponentPresentation(
        label: ['en' => 'Map markers', 'pl' => 'Markery mapy'],
        description: ['en' => 'Place, move and cluster multiple locations.', 'pl' => 'Dodawaj, przesuwaj i grupuj wiele lokalizacji.'],
        category: CatalogCategory::Location,
        keywords: ['map', 'location', 'map-markers'], icon: 'hugeicons-map-pin-plus',
        author: 'FLEX', official: true, demoClass: MapMarkersPlayground::class,
        demoSlug: 'map-markers', sort: 343,
        examples: [CodeExample::php(dirname(__DIR__).'/examples/map-markers/usage.php')],
    ),
);
