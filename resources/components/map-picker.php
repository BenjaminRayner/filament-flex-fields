<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;
use Bjanczak\FilamentFlexFields\Support\Playground\MapPickerPlayground;

return new ComponentDefinition(
    id: 'map-picker',
    kind: ComponentKind::Field,
    styles: ['map-picker'],
    stylesFrom: ['flex-text-input', 'location-controls'],
    uses: ['location-geocoding'],
    entry: 'map-picker',
    capabilities: [],
    publications: [new PublishedResource('track-markers', 'resources/dist/assets/location', 'filament-flex-fields-assets/location')],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.map-picker.label',
        description: 'filament-flex-fields::catalog.components.map-picker.description',
        category: CatalogCategory::Location,
        keywords: ['map', 'picker', 'gps', 'geojson', 'markers', 'mapa', 'adres'],
        icon: 'hugeicons-maps-location-02',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/map-coordinates/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/map-display/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/map-picker/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/map-picker/address-fields.php'),
        ],
        demoClass: MapPickerPlayground::class,
        demoSlug: 'map-picker',
        sort: 340,
        documentation: 'mappickerfield.md',
        popularRank: 2,
        newAndNoteworthy: true,
    ),
);
