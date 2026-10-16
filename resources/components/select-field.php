<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\BuiltInChildContracts;
use Bjanczak\FilamentFlexFields\Support\Playground\SelectPlayground;

return new ComponentDefinition(
    id: 'select-field',
    kind: ComponentKind::Field,
    styles: ['select-field'],
    stylesFrom: ['teleported-menu'],
    entry: 'select-field',
    capabilities: [],
    childContract: BuiltInChildContracts::select(),
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.select-field.label',
        description: 'filament-flex-fields::catalog.components.select-field.description',
        category: CatalogCategory::Pickers,
        keywords: ['select', 'field', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-dropdown-field-type',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/select-field/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/select-field/appearance.php'),
            CodeExample::php(dirname(__DIR__).'/examples/select-field/inline-search.php'),
            CodeExample::php(dirname(__DIR__).'/examples/select-field/create-options.php'),
            CodeExample::php(dirname(__DIR__).'/examples/select-field/dependent-options.php'),
            CodeExample::php(dirname(__DIR__).'/examples/select-field/remote-pagination.php'),
        ],
        demoClass: SelectPlayground::class,
        demoSlug: 'select-field',
        sort: 160,
        documentation: 'selectfield.md',
        popularRank: 0,
    ),
);
