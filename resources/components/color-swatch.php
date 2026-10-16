<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ColorSwatchPlayground;

return new ComponentDefinition(
    id: 'color-swatch',
    kind: ComponentKind::Field,
    styles: ['color-swatch'],
    stylesFrom: [],
    entry: 'color-swatch',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.color-swatch.label',
        description: 'filament-flex-fields::catalog.components.color-swatch.description',
        category: CatalogCategory::Colors,
        keywords: ['color', 'swatch', 'hex', 'rgb', 'hsl', 'swatches', 'kolor'],
        icon: 'hugeicons-swatch-book',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/color-swatch/usage.php'),
        ],
        demoClass: ColorSwatchPlayground::class,
        demoSlug: 'color-swatch',
        sort: 290,
        documentation: 'colorswatchfield.md',
    ),
);
