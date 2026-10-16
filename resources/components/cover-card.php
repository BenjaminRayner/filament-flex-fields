<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\CoverCardPlayground;

return new ComponentDefinition(
    id: 'cover-card',
    kind: ComponentKind::Display,
    styles: ['cover-card'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.cover-card.label',
        description: 'filament-flex-fields::catalog.components.cover-card.description',
        category: CatalogCategory::Collections,
        keywords: ['cover', 'card', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-text-to-image',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/cover-card/usage.php'),
        ],
        demoClass: CoverCardPlayground::class,
        demoSlug: 'cover-card',
        sort: 410,
        documentation: 'covercard.md',
    ),
);
