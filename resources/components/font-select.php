<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FontSelectPlayground;

return new ComponentDefinition(
    id: 'font-select',
    kind: ComponentKind::Field,
    styles: ['font-select'],
    uses: ['select-field'],
    entry: 'font-select',
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.font-select.label',
        description: 'filament-flex-fields::catalog.components.font-select.description',
        category: CatalogCategory::Pickers,
        keywords: ['font', 'typography', 'google fonts', 'preview', 'virtualization'],
        icon: 'hugeicons-type-outline',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/font-select/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/font-select/application.php'),
        ],
        demoClass: FontSelectPlayground::class,
        demoSlug: 'font-select',
        sort: 32,
        documentation: 'font-select.md',
    ),
);
