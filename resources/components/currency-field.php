<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\CurrencyFieldPlayground;

return new ComponentDefinition(
    id: 'currency-field',
    kind: ComponentKind::Field,
    styles: ['currency-field'],
    stylesFrom: ['flex-text-input'],
    uses: ['select-field'],
    entry: 'currency-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.currency-field.label',
        description: 'filament-flex-fields::catalog.components.currency-field.description',
        category: CatalogCategory::TextInput,
        keywords: ['currency', 'field', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-dollar-circle',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/currency-field/usage.php'),
        ],
        demoClass: CurrencyFieldPlayground::class,
        demoSlug: 'currency-field',
        sort: 50,
        documentation: 'currencyfield.md',
    ),
);
