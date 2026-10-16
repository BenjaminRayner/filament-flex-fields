<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\TimezoneFieldPlayground;

return new ComponentDefinition(
    id: 'timezone-field',
    kind: ComponentKind::Field,
    styles: [],
    uses: ['select-field'],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.timezone-field.label',
        description: 'filament-flex-fields::catalog.components.timezone-field.description',
        category: CatalogCategory::Pickers,
        keywords: ['timezone', 'field', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-time-zone',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/timezone-field/selection.php', 'Search and browser defaults'),
            CodeExample::php(dirname(__DIR__).'/examples/timezone-field/availability.php', 'Restricted and excluded timezones'),
            CodeExample::php(dirname(__DIR__).'/examples/timezone-field/presentation.php', 'Labels and sizes'),
            CodeExample::php(dirname(__DIR__).'/examples/timezone-field/validation.php', 'Validation: required and whitelist'),
            CodeExample::php(dirname(__DIR__).'/examples/timezone-field/states.php', 'Variants and locked values'),
        ],
        demoClass: TimezoneFieldPlayground::class,
        demoSlug: 'timezone-field',
        sort: 40,
        documentation: 'timezonefield.md',
    ),
);
