<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\HoldConfirmPlayground;

return new ComponentDefinition(
    id: 'hold-confirm', kind: ComponentKind::Action,
    uses: ['hold-confirm-action'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.hold-confirm.label',
        description: 'filament-flex-fields::catalog.components.hold-confirm.description',
        category: CatalogCategory::Buttons,
        keywords: ['hold', 'confirm', 'morph', 'countdown', 'undo', 'inline confirmation'],
        icon: 'hugeicons-cursor-hold-01',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/hold-confirm/usage.php'),
        ],
        demoClass: HoldConfirmPlayground::class,
        demoSlug: 'hold-confirm',
        sort: 185,
        documentation: 'hold-confirm-action.md',
    ),
);
