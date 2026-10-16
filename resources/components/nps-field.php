<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;
use Bjanczak\FilamentFlexFields\Support\Playground\NpsFieldPlayground;

return new ComponentDefinition(
    id: 'nps-field',
    kind: ComponentKind::Field,
    styles: ['nps-field'],
    stylesFrom: ['segment-control'],
    entry: 'nps-field',
    publications: [new PublishedResource(
        id: 'resources', sourceDirectory: 'resources/dist/assets/nps-field', publicDirectory: 'filament-flex-fields-assets/nps-field',
    )],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.nps-field.label',
        description: 'filament-flex-fields::catalog.components.nps-field.description',
        category: CatalogCategory::Controls,
        keywords: ['nps', 'field'],
        icon: 'hugeicons-chart-column',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/nps-field/usage.php'),
        ],
        demoClass: NpsFieldPlayground::class,
        demoSlug: 'nps-field',
        sort: 95,
        documentation: 'nps-field.md',
    ),
);
