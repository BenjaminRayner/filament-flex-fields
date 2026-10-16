<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\AddressAutocompletePlayground;

return new ComponentDefinition(
    id: 'address-autocomplete',
    kind: ComponentKind::Field,
    styles: ['address-autocomplete'],
    stylesFrom: ['flex-text-input'],
    uses: ['location-select'],
    entry: 'address-autocomplete',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.address-autocomplete.label',
        description: 'filament-flex-fields::catalog.components.address-autocomplete.description',
        category: CatalogCategory::Location,
        keywords: ['address', 'autocomplete', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-real-estate-01',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/address-autocomplete/usage.php'),
        ],
        demoClass: AddressAutocompletePlayground::class,
        demoSlug: 'address-autocomplete',
        sort: 350,
        documentation: 'addressautocompletefield.md',
        newAndNoteworthy: true,
    ),
);
