<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapCoordinatesPlayground;

return new ComponentDefinition(
    id: 'map-coordinates', kind: ComponentKind::Field, uses: ['map-picker'],
    catalog: new ComponentPresentation(
        listed: false,
        label: ['en' => 'Map coordinates', 'pl' => 'Współrzędne mapy'],
        description: ['en' => 'Choose coordinates without address geocoding.', 'pl' => 'Wybieraj współrzędne bez geokodowania adresu.'],
        category: CatalogCategory::Location,
        keywords: ['map', 'location', 'map-coordinates'], icon: 'hugeicons-location-01',
        author: 'FLEX', official: true, demoClass: MapCoordinatesPlayground::class,
        demoSlug: 'map-coordinates', sort: 345,
        examples: [CodeExample::php(dirname(__DIR__).'/examples/map-coordinates/usage.php')],
    ),
);
