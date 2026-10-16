<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\PriceRangePlayground;

return new ComponentDefinition(
    id: 'price-range',
    kind: ComponentKind::Field,
    styles: ['price-range'],
    stylesFrom: [],
    entry: 'price-range',
    capabilities: ['inputs' => ['text-input-presentation']],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.price-range.label',
        description: 'filament-flex-fields::catalog.components.price-range.description',
        category: CatalogCategory::Controls,
        keywords: ['price', 'range'],
        icon: 'hugeicons-bitcoin-graph',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/price-range/usage.php'),
        ],
        demoClass: PriceRangePlayground::class,
        demoSlug: 'price-range',
        sort: 220,
        documentation: 'pricerangefield.md',
    ),
);
