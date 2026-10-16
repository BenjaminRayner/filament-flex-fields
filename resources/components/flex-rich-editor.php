<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexRichEditorPlayground;

return new ComponentDefinition(
    id: 'flex-rich-editor', kind: ComponentKind::Field,
    uses: ['rich-editor-field'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.flex-rich-editor.label',
        description: 'filament-flex-fields::catalog.components.flex-rich-editor.description',
        category: CatalogCategory::TextInput,
        keywords: ['flex', 'rich', 'editor', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-text-creation',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/flex-rich-editor/usage.php'),
        ],
        demoClass: FlexRichEditorPlayground::class,
        demoSlug: 'flex-rich-editor',
        sort: 232,
        documentation: 'flex-rich-editor.md',
    ),
);
