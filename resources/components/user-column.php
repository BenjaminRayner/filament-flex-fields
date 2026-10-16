<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\UserColumnPlayground;

return new ComponentDefinition(
    id: 'user-column',
    kind: ComponentKind::Column,
    styles: ['user-column'],
    stylesFrom: ['user-display'],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.user-column.label',
        description: 'filament-flex-fields::catalog.components.user-column.description',
        category: CatalogCategory::DataDisplay,
        keywords: ['user', 'column', 'table', 'display', 'tabela'],
        icon: 'hugeicons-user-square',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/user-column/usage.php'),
        ],
        demoClass: UserColumnPlayground::class,
        demoSlug: 'user-column',
        groupedWith: 'user-select',
        sort: 180,
        documentation: 'usercolumn.md',
    ),
);
