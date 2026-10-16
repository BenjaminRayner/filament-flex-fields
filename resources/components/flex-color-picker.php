<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexColorPickerPlayground;

return new ComponentDefinition(
    id: 'flex-color-picker',
    kind: ComponentKind::Field,
    styles: ['flex-color-picker'],
    stylesFrom: ['flex-text-input', 'teleported-menu'],
    entry: 'flex-color-picker',
    capabilities: ['format-selector' => ['select-field']],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.flex-color-picker.label',
        description: 'filament-flex-fields::catalog.components.flex-color-picker.description',
        category: CatalogCategory::Colors,
        keywords: ['flex', 'color', 'picker', 'hex', 'rgb', 'hsl', 'swatches', 'kolor'],
        icon: 'hugeicons-paint-bucket',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/flex-color-picker/usage.php'),
        ],
        demoClass: FlexColorPickerPlayground::class,
        demoSlug: 'flex-color-picker',
        sort: 300,
        documentation: 'flexcolorpickerfield.md',
        popularRank: 3,
        newAndNoteworthy: true,
    ),
);
