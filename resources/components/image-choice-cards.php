<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ImageChoiceCardsPlayground;

return new ComponentDefinition(
    id: 'image-choice-cards',
    kind: ComponentKind::Field,
    styles: ['image-choice-cards'],
    stylesFrom: [],
    entry: 'image-choice-cards',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.image-choice-cards.label',
        description: 'filament-flex-fields::catalog.components.image-choice-cards.description',
        category: CatalogCategory::Collections,
        keywords: ['image', 'choice', 'cards', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-camera-01',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/image-choice-cards/usage.php'),
        ],
        demoClass: ImageChoiceCardsPlayground::class,
        demoSlug: 'image-choice-cards',
        sort: 75,
        documentation: 'imagechoicecards.md',
    ),
);
