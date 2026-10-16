<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\LinkPreviewFieldPlayground;

return new ComponentDefinition(
    id: 'link-preview-field',
    kind: ComponentKind::Field,
    styles: ['link-preview-field'],
    stylesFrom: ['text-input-presentation'],
    entry: 'link-preview-field',
    capabilities: [],
    routes: require dirname(__DIR__).'/routes/link-preview-field.php',
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.link-preview-field.label',
        description: 'filament-flex-fields::catalog.components.link-preview-field.description',
        category: CatalogCategory::Media,
        keywords: ['link', 'preview', 'field'],
        icon: 'hugeicons-app-window',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/link-preview-field/usage.php'),
        ],
        demoClass: LinkPreviewFieldPlayground::class,
        demoSlug: 'link-preview-field',
        sort: 345,
        documentation: 'link-preview-field.md',
    ),
);
