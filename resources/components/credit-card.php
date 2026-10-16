<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\CreditCardPlayground;

return new ComponentDefinition(
    id: 'credit-card',
    kind: ComponentKind::Field,
    styles: ['credit-card'],
    stylesFrom: ['text-input-presentation'],
    entry: 'credit-card',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.credit-card.label',
        description: 'filament-flex-fields::catalog.components.credit-card.description',
        category: CatalogCategory::TextInput,
        keywords: ['credit', 'card', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-credit-card',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/credit-card/usage.php'),
        ],
        demoClass: CreditCardPlayground::class,
        demoSlug: 'credit-card',
        sort: 280,
        documentation: 'creditcardfield.md',
    ),
);
