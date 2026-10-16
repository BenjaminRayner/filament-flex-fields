<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\RatingPlayground;

return new ComponentDefinition(
    id: 'rating', kind: ComponentKind::Field,
    uses: ['rating-field'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.rating.label',
        description: 'filament-flex-fields::catalog.components.rating.description',
        category: CatalogCategory::Controls,
        keywords: ['rating'],
        icon: 'hugeicons-star',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/rating/usage.php'),
        ],
        demoClass: RatingPlayground::class,
        demoSlug: 'rating',
        sort: 190,
        documentation: 'ratingfield.md',
    ),
);
