<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\StatusChipColumnPlayground;

return new ComponentDefinition(
    id: 'status-chip-column',
    kind: ComponentKind::Column,
    styles: ['status-chip-column'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'Status chip column',
        description: 'Read-only table presentation with configurable appearance and empty states.',
        category: CatalogCategory::DataDisplay,
        keywords: ['table', 'column', 'status-chip-column'],
        icon: 'hugeicons-label',
        author: 'FLEX',
        official: true,
        examples: [CodeExample::php(dirname(__DIR__).'/examples/status-chip-column/usage.php')],
        demoClass: StatusChipColumnPlayground::class,
        demoSlug: 'status-chip-column',
        documentation: 'status-chip-column.md',
    ),
);
