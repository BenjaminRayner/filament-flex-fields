<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\TrackSliderPlayground;

return new ComponentDefinition(
    id: 'track-slider',
    kind: ComponentKind::Field,
    styles: ['track-slider'],
    stylesFrom: [],
    entry: 'track-slider',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.track-slider.label',
        description: 'filament-flex-fields::catalog.components.track-slider.description',
        category: CatalogCategory::Controls,
        keywords: ['track', 'slider'],
        icon: 'hugeicons-form',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/track-slider/usage.php'),
        ],
        demoClass: TrackSliderPlayground::class,
        demoSlug: 'track-slider',
        sort: 120,
        documentation: 'trackslider.md',
    ),
);
