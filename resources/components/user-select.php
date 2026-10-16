<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\UserSelectPlayground;

return new ComponentDefinition(
    id: 'user-select',
    kind: ComponentKind::Field,
    styles: ['user-select'],
    stylesFrom: ['tag-chips', 'user-display'],
    uses: ['select-field'],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.user-select.label',
        description: 'filament-flex-fields::catalog.components.user-select.description',
        category: CatalogCategory::Pickers,
        keywords: ['user', 'select', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-co-present',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/user-select/usage.php'),
        ],
        demoClass: UserSelectPlayground::class,
        demoSlug: 'user-select',
        sort: 170,
        documentation: 'userselect.md',
        popularRank: 5,
    ),
);
