<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexSliderPlayground;

return new ComponentDefinition(
    id: 'flex-slider',
    kind: ComponentKind::Field,
    styles: ['flex-slider'],
    stylesFrom: [],
    entry: 'flex-slider',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.flex-slider.label',
        description: 'filament-flex-fields::catalog.components.flex-slider.description',
        category: CatalogCategory::Controls,
        keywords: ['flex', 'slider'],
        icon: 'hugeicons-settings-05',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/flex-slider/usage.php'),
        ],
        demoClass: FlexSliderPlayground::class,
        demoSlug: 'flex-slider',
        sort: 130,
        documentation: 'flexslider.md',
    ),
);
