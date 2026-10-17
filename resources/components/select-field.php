<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\BuiltInChildContracts;
use Bjanczak\FilamentFlexFields\Support\Components\Guidance\ComponentGuidance;
use Bjanczak\FilamentFlexFields\Support\Playground\SelectPlayground;

return new ComponentDefinition(
    id: 'select-field',
    kind: ComponentKind::Field,
    styles: ['select-field'],
    stylesFrom: ['teleported-menu'],
    entry: 'select-field',
    capabilities: [],
    childContract: BuiltInChildContracts::select(),
    guidance: new ComponentGuidance(
        summary: 'Choose stable values with configurable rendering, virtual rows and paginated search.',
        persistence: 'Persist the selected scalar value, or an array for multiple selection. Display labels and row metadata are not the stored identity.',
        instructions: ['For large queries pair paginatedSearchResults() with getSearchResultsPageUsing(); virtualization does not paginate SQL.',
            'Apply the same record and tenant scope to search, selected-label resolution and submitted-ID validation.',
            'Return bounded items with stable value/label, an advancing cursor and hasMore. Do not preload the entire table for one selected label.'],
        verification: ['Select, save and reopen a value without first opening the menu.', 'Test fast query changes, additional pages, keyboard navigation and modal reuse.'],
    ),
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
            CodeExample::php(dirname(__DIR__).'/recipes/scoped-select/app/Support/AssigneePicker.php', 'Authorized paginated picker'),
        ],
        demoClass: SelectPlayground::class,
        demoSlug: 'select-field',
        sort: 160,
        documentation: 'selectfield.md',
        popularRank: 0,
    ),
);
