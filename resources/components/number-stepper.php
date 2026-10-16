<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\NumberStepperPlayground;

return new ComponentDefinition(
    id: 'number-stepper',
    kind: ComponentKind::Field,
    styles: ['number-stepper'],
    stylesFrom: [],
    entry: 'number-stepper',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.number-stepper.label',
        description: 'filament-flex-fields::catalog.components.number-stepper.description',
        category: CatalogCategory::Controls,
        keywords: ['number', 'stepper'],
        icon: 'hugeicons-input-numeric',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/number-stepper/usage.php'),
        ],
        demoClass: NumberStepperPlayground::class,
        demoSlug: 'number-stepper',
        sort: 60,
        documentation: 'numberstepper.md',
    ),
);
