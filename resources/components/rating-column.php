<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\RatingColumnPlayground;

return new ComponentDefinition(
    id: 'rating-column',
    kind: ComponentKind::Column,
    styles: ['rating-column'],
    stylesFrom: ['rating-visuals'],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.rating-column.label',
        description: 'filament-flex-fields::catalog.components.rating-column.description',
        category: CatalogCategory::DataDisplay,
        keywords: ['rating', 'column', 'table', 'display', 'tabela'],
        icon: 'hugeicons-star-half',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/rating-column/usage.php'),
        ],
        demoClass: RatingColumnPlayground::class,
        demoSlug: 'rating-column',
        groupedWith: 'rating',
        sort: 200,
        documentation: 'ratingcolumn.md',
    ),
);
