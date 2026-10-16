<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FieldIntelligencePlayground;

return new ComponentDefinition(
    id: 'field-intelligence', kind: ComponentKind::Display,
    styles: ['field-intelligence'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.field-intelligence.label',
        description: 'filament-flex-fields::catalog.components.field-intelligence.description',
        category: CatalogCategory::Guides,
        keywords: ['field', 'intelligence', 'formula', 'calculations', 'validation', 'dependencies'],
        icon: 'hugeicons-ai-brain-01',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/validated-plan.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/calculated-fields.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/incremental-updates.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/tax-calculation.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/progress-formula.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/profit-margin.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/formula-playground.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/cycle-detection.php'),
            CodeExample::php(dirname(__DIR__).'/examples/field-intelligence/allowed-functions.php'),
        ],
        demoClass: FieldIntelligencePlayground::class,
        demoSlug: 'field-intelligence',
        sort: 14,
        documentation: 'field-intelligence.md',
    ),
);
