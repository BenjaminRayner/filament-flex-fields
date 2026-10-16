<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexTextInputPlayground;

return new ComponentDefinition(
    id: 'flex-text-input',
    kind: ComponentKind::Field,
    styles: [],
    stylesFrom: ['text-input-presentation'],
    entry: 'flex-text-input',
    capabilities: ['icon-picker' => ['icon-picker-field'], 'emoji-picker' => ['emoji-picker'], 'suggestions' => ['text-input-suggestions']],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.flex-text-input.label',
        description: 'filament-flex-fields::catalog.components.flex-text-input.description',
        category: CatalogCategory::TextInput,
        keywords: ['flex', 'text', 'input', 'validation', 'tekst'],
        icon: 'hugeicons-input-short-text',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/overview.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/native.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/counters.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/editing.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/actions.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/password.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/tools.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/assistance.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/appearance.php'),
            CodeExample::php(dirname(__DIR__).'/examples/flex-text-input/async-and-native-actions.php'),
        ],
        demoClass: FlexTextInputPlayground::class,
        demoSlug: 'flex-text-input',
        sort: 240,
        documentation: 'flextextinput.md',
        popularRank: 1,
    ),
);
