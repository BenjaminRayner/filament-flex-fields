<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;
use Bjanczak\FilamentFlexFields\Support\Playground\BarcodeScannerFieldPlayground;

return new ComponentDefinition(
    id: 'barcode-scanner-field',
    kind: ComponentKind::Field,
    styles: ['barcode-scanner-field'],
    stylesFrom: ['text-input-presentation', 'camera-surface'],
    entry: 'barcode-scanner-field',
    publications: [new PublishedResource(
        id: 'resources', sourceDirectory: 'resources/dist/assets/barcode-scanner-field', publicDirectory: 'filament-flex-fields-assets/barcode-scanner-field',
    )],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.barcode-scanner-field.label',
        description: 'filament-flex-fields::catalog.components.barcode-scanner-field.description',
        category: CatalogCategory::Media,
        keywords: ['barcode', 'scanner', 'field'],
        icon: 'hugeicons-qr-code',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/barcode-scanner-field/usage.php'),
        ],
        demoClass: BarcodeScannerFieldPlayground::class,
        demoSlug: 'barcode-scanner-field',
        sort: 346,
        documentation: 'barcode-scanner-field.md',
    ),
);
