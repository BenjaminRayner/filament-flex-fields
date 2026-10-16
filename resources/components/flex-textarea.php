<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexTextareaPlayground;

return new ComponentDefinition(
    id: 'flex-textarea',
    kind: ComponentKind::Field,
    styles: ['flex-textarea'],
    stylesFrom: [],
    entry: 'flex-textarea',
    capabilities: ['emoji-picker' => ['emoji-picker'], 'toolbar-select' => ['select-field']],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.flex-textarea.label',
        description: 'filament-flex-fields::catalog.components.flex-textarea.description',
        category: CatalogCategory::TextInput,
        keywords: ['flex', 'textarea', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-text-circle',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/flex-textarea/usage.php'),
        ],
        demoClass: FlexTextareaPlayground::class,
        demoSlug: 'flex-textarea',
        sort: 230,
        documentation: 'flextextareafield.md',
    ),
);
