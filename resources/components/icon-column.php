<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\IconColumnPlayground;

return new ComponentDefinition(
    id: 'icon-column',
    kind: ComponentKind::Column,
    styles: ['icon-column'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.icon-column.label',
        description: 'filament-flex-fields::catalog.components.icon-column.description',
        category: CatalogCategory::DataDisplay,
        keywords: ['icon', 'column', 'table', 'display', 'tabela'],
        icon: 'hugeicons-star-square',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/icon-column/usage.php'),
        ],
        demoClass: IconColumnPlayground::class,
        demoSlug: 'icon-column',
        groupedWith: 'icon-picker-field',
        sort: 306,
        documentation: 'iconcolumn.md',
    ),
);
