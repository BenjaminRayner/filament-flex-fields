<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapZonesPlayground;

return new ComponentDefinition(
    id: 'map-zones', kind: ComponentKind::Field, uses: ['map-picker'],
    catalog: new ComponentPresentation(
        label: ['en' => 'Map zones', 'pl' => 'Strefy mapy'],
        description: ['en' => 'Draw and edit polygons, rectangles and circles.', 'pl' => 'Rysuj i edytuj wielokąty, prostokąty i koła.'],
        category: CatalogCategory::Location,
        keywords: ['map', 'location', 'map-zones'], icon: 'hugeicons-maps-circle-02',
        author: 'FLEX', official: true, demoClass: MapZonesPlayground::class,
        demoSlug: 'map-zones', sort: 342,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/map-editor/usage.php'), CodeExample::php(dirname(__DIR__).'/examples/map-zones/usage.php')],
    ),
);
