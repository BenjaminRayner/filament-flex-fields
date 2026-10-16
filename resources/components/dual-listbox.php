<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\DualListboxPlayground;

return new ComponentDefinition(
    id: 'dual-listbox',
    kind: ComponentKind::Field,
    styles: ['dual-listbox'],
    stylesFrom: ['text-input-presentation', 'checklist-indicator'],
    entry: 'dual-listbox',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.dual-listbox.label',
        description: 'filament-flex-fields::catalog.components.dual-listbox.description',
        category: CatalogCategory::Pickers,
        keywords: ['dual', 'listbox', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-layout-2-column',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/dual-listbox/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/dual-listbox/configuration.php'),
        ],
        demoClass: DualListboxPlayground::class,
        demoSlug: 'dual-listbox',
        sort: 210,
        documentation: 'duallistboxfield.md',
    ),
);
