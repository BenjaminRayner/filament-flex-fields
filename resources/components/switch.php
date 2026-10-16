<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\SwitchPlayground;

return new ComponentDefinition(
    id: 'switch',
    kind: ComponentKind::Field,
    styles: ['switch'],
    stylesFrom: [],
    entry: 'switch',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.switch.label',
        description: 'filament-flex-fields::catalog.components.switch.description',
        category: CatalogCategory::Controls,
        keywords: ['switch'],
        icon: 'hugeicons-toggle-on',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/switch/usage.php', 'Rows and cards'),
            CodeExample::php(dirname(__DIR__).'/examples/switch/appearance.php', 'Sizes, colors and icons'),
            CodeExample::php(dirname(__DIR__).'/examples/switch/inline-and-validation.php', 'Inline, validation and live updates'),
        ],
        demoClass: SwitchPlayground::class,
        demoSlug: 'switch',
        sort: 150,
        documentation: 'switchfield.md',
    ),
);
