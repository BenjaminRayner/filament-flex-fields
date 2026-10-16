<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\CountryMapPlayground;

return new ComponentDefinition(
    id: 'country-map-field',
    kind: ComponentKind::Field,
    styles: ['country-map-field'],
    stylesFrom: ['selection-indicator', 'location-controls'],
    uses: ['select-field'],
    entry: 'country-map-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.country-map-field.label',
        description: 'filament-flex-fields::catalog.components.country-map-field.description',
        category: CatalogCategory::Location,
        keywords: ['country', 'map', 'field', 'gps', 'geojson', 'markers', 'mapa', 'adres'],
        icon: 'hugeicons-global-search',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/country-map-field/usage.php'),
        ],
        demoClass: CountryMapPlayground::class,
        demoSlug: 'country-map-field',
        sort: 341,
        documentation: 'country-map-field.md',
        newAndNoteworthy: true,
    ),
);
