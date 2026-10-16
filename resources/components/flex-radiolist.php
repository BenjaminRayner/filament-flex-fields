<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexRadiolistPlayground;

return new ComponentDefinition(
    id: 'flex-radiolist', kind: ComponentKind::Field,
    stylesFrom: ['flex-checklist'],
    entry: 'flex-radiolist',
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.flex-radiolist.label',
        description: 'filament-flex-fields::catalog.components.flex-radiolist.description',
        category: CatalogCategory::Collections,
        keywords: ['flex', 'radiolist', 'multiple', 'choices', 'list', 'wybór'],
        icon: 'hugeicons-radio-button',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/flex-radiolist/usage.php'),
        ],
        demoClass: FlexRadiolistPlayground::class,
        demoSlug: 'flex-radiolist',
        sort: 390,
        documentation: 'flexradiolist.md',
    ),
);
