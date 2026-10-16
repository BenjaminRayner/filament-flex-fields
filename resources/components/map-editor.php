<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapEditorPlayground;

return new ComponentDefinition(
    id: 'map-editor', kind: ComponentKind::Field, uses: ['map-picker'],
    catalog: new ComponentPresentation(
        listed: false,
        label: ['en' => 'Map editor', 'pl' => 'Edytor mapy'],
        description: ['en' => 'Edit points, lines and zones in one geometry field.', 'pl' => 'Edytuj punkty, linie i strefy w jednym polu.'],
        category: CatalogCategory::Location,
        keywords: ['map', 'location', 'map-editor'], icon: 'hugeicons-maps-editing',
        author: 'FLEX', official: true, demoClass: MapEditorPlayground::class,
        demoSlug: 'map-editor', sort: 344,
        examples: [CodeExample::php(dirname(__DIR__).'/examples/map-editor/usage.php')],
    ),
);
