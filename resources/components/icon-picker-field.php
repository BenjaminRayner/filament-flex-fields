<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\IconPickerFieldPlayground;

return new ComponentDefinition(
    id: 'icon-picker-field',
    kind: ComponentKind::Field,
    styles: ['icon-picker-field'],
    uses: ['select-field'],
    entry: 'icon-picker-field',
    capabilities: ['inline' => ['text-input-presentation']],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.icon-picker-field.label',
        description: 'filament-flex-fields::catalog.components.icon-picker-field.description',
        category: CatalogCategory::Pickers,
        keywords: ['icon', 'picker', 'field', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-geometric-shapes-01',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/icon-picker-field/usage.php'),
        ],
        demoClass: IconPickerFieldPlayground::class,
        demoSlug: 'icon-picker-field',
        sort: 305,
        documentation: 'icon-picker-field.md',
    ),
);
