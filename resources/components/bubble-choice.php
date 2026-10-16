<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\BubbleChoicePlayground;

return new ComponentDefinition(
    id: 'bubble-choice',
    kind: ComponentKind::Field,
    styles: ['bubble-choice'],
    stylesFrom: [],
    entry: 'bubble-choice',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.bubble-choice.label',
        description: 'filament-flex-fields::catalog.components.bubble-choice.description',
        category: CatalogCategory::Pickers,
        keywords: ['bubble', 'choice', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-bubbles',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/bubble-choice/usage.php'),
        ],
        demoClass: BubbleChoicePlayground::class,
        demoSlug: 'bubble-choice',
        sort: 215,
        documentation: 'bubblechoicefield.md',
    ),
);
