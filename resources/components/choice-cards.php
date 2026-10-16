<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ChoiceCardsPlayground;

return new ComponentDefinition(
    id: 'choice-cards',
    kind: ComponentKind::Field,
    styles: ['choice-cards'],
    stylesFrom: ['selection-indicator'],
    entry: 'choice-cards',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.choice-cards.label',
        description: 'filament-flex-fields::catalog.components.choice-cards.description',
        category: CatalogCategory::Collections,
        keywords: ['choice', 'cards', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-grid-view',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/choice-cards/usage.php'),
        ],
        demoClass: ChoiceCardsPlayground::class,
        demoSlug: 'choice-cards',
        sort: 70,
        documentation: 'choicecards.md',
    ),
);
