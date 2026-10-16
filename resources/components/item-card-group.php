<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ItemCardGroupPlayground;

return new ComponentDefinition(
    id: 'item-card-group', kind: ComponentKind::Display,
    uses: ['item-card'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.item-card-group.label',
        description: 'filament-flex-fields::catalog.components.item-card-group.description',
        category: CatalogCategory::Collections,
        keywords: ['item', 'card', 'group', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-collections-bookmark',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/item-card-group/usage.php'),
        ],
        demoClass: ItemCardGroupPlayground::class,
        demoSlug: 'item-card-group',
        sort: 400,
        documentation: 'itemcardgroup.md',
    ),
);
