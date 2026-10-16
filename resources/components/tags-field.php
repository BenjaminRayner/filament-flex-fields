<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\TagsFieldPlayground;

return new ComponentDefinition(
    id: 'tags-field',
    kind: ComponentKind::Field,
    styles: ['tags-field'],
    stylesFrom: ['flex-text-input', 'tag-chips'],
    uses: ['select-field'],
    entry: 'tags-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.tags-field.label',
        description: 'filament-flex-fields::catalog.components.tags-field.description',
        category: CatalogCategory::Pickers,
        keywords: ['tags', 'field', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-tag-01',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/tags-field/usage.php'),
        ],
        demoClass: TagsFieldPlayground::class,
        demoSlug: 'tags-field',
        sort: 398,
        documentation: 'tags-field.md',
    ),
);
