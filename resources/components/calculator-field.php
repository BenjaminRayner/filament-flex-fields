<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\CalculatorFieldPlayground;

return new ComponentDefinition(
    id: 'calculator-field',
    kind: ComponentKind::Field,
    styles: ['calculator-field'],
    uses: ['flex-text-input', 'calculator-panel'],
    entry: 'calculator-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.calculator-field.label',
        description: 'filament-flex-fields::catalog.components.calculator-field.description',
        category: CatalogCategory::TextInput,
        keywords: ['calculator', 'field', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-calculate',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/calculator-field/usage.php'),
        ],
        demoClass: CalculatorFieldPlayground::class,
        demoSlug: 'calculator-field',
        sort: 55,
        documentation: 'calculator-field.md',
    ),
);
