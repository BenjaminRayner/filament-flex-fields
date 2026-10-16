<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\SlugFieldPlayground;

return new ComponentDefinition(
    id: 'slug-field',
    kind: ComponentKind::Field,
    styles: ['slug-field'],
    uses: ['flex-text-input', 'segment-control'],
    entry: 'slug-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.slug-field.label',
        description: 'filament-flex-fields::catalog.components.slug-field.description',
        category: CatalogCategory::TextInput,
        keywords: ['slug', 'field', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-link',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/slug-field/usage.php'),
        ],
        demoClass: SlugFieldPlayground::class,
        demoSlug: 'slug-field',
        sort: 250,
        documentation: 'slugfield-and-titleslugfield.md',
    ),
);
