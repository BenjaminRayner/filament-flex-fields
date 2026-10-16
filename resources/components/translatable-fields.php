<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\TranslatableFieldsPlayground;

return new ComponentDefinition(
    id: 'translatable-fields', kind: ComponentKind::Field,
    uses: ['segment-tabs'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.translatable-fields.label',
        description: 'filament-flex-fields::catalog.components.translatable-fields.description',
        category: CatalogCategory::TextInput,
        keywords: ['translatable', 'fields', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-translate',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/translatable-fields/usage.php'),
            CodeExample::php(dirname(__DIR__).'/examples/translatable-fields/spatie.php'),
        ],
        demoClass: TranslatableFieldsPlayground::class,
        demoSlug: 'translatable-fields',
        sort: 260,
        documentation: 'translatablefields.md',
    ),
);
