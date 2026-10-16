<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexChecklistPlayground;

return new ComponentDefinition(
    id: 'flex-checklist',
    kind: ComponentKind::Field,
    styles: ['flex-checklist'],
    stylesFrom: ['checklist-indicator'],
    entry: 'flex-checklist',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.flex-checklist.label',
        description: 'filament-flex-fields::catalog.components.flex-checklist.description',
        category: CatalogCategory::Collections,
        keywords: ['flex', 'checklist', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-check-list',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/flex-checklist/usage.php'),
        ],
        demoClass: FlexChecklistPlayground::class,
        demoSlug: 'flex-checklist',
        sort: 380,
        documentation: 'flexchecklist.md',
    ),
);
