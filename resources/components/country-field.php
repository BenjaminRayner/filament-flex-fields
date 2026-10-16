<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\CountryFieldPlayground;

return new ComponentDefinition(
    id: 'country-field',
    kind: ComponentKind::Field,
    styles: [],
    uses: ['select-field', 'country-flags'],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.country-field.label',
        description: 'filament-flex-fields::catalog.components.country-field.description',
        category: CatalogCategory::Pickers,
        keywords: ['country', 'field', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-globe-02',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/country-field/usage.php'),
        ],
        demoClass: CountryFieldPlayground::class,
        demoSlug: 'country-field',
        sort: 30,
        documentation: 'countryfield.md',
    ),
);
