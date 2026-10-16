<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\PhoneFieldPlayground;

return new ComponentDefinition(
    id: 'phone-field',
    kind: ComponentKind::Field,
    styles: ['phone-field'],
    stylesFrom: ['text-input-presentation'],
    uses: ['select-field', 'country-flags'],
    entry: 'phone-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.phone-field.label',
        description: 'filament-flex-fields::catalog.components.phone-field.description',
        category: CatalogCategory::Pickers,
        keywords: ['phone', 'field', 'search', 'selection', 'dropdown', 'wyszukiwanie', 'wybór'],
        icon: 'hugeicons-call-02',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/phone-field/usage.php'),
        ],
        demoClass: PhoneFieldPlayground::class,
        demoSlug: 'phone-field',
        sort: 20,
        documentation: 'phonefield.md',
    ),
);
