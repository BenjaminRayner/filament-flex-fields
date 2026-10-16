<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ChoiceCheckboxCardsPlayground;

return new ComponentDefinition(
    id: 'choice-checkbox-cards', kind: ComponentKind::Field,
    uses: ['choice-cards'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.choice-checkbox-cards.label',
        description: 'filament-flex-fields::catalog.components.choice-checkbox-cards.description',
        category: CatalogCategory::Collections,
        keywords: ['choice', 'checkbox', 'cards', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-checkmark-square-01',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/choice-checkbox-cards/usage.php'),
        ],
        demoClass: ChoiceCheckboxCardsPlayground::class,
        demoSlug: 'choice-checkbox-cards',
        sort: 80,
        documentation: 'choicecheckboxcards.md',
    ),
);
