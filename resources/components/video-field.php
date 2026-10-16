<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\VideoFieldPlayground;

return new ComponentDefinition(
    id: 'video-field',
    kind: ComponentKind::Field,
    styles: ['video-field'],
    stylesFrom: [],
    entry: 'video-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.video-field.label',
        description: 'filament-flex-fields::catalog.components.video-field.description',
        category: CatalogCategory::Media,
        keywords: ['video', 'field'],
        icon: 'hugeicons-video-ai',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/video-field/usage.php'),
        ],
        demoClass: VideoFieldPlayground::class,
        demoSlug: 'video-field',
        sort: 320,
        documentation: 'videofield.md',
    ),
);
