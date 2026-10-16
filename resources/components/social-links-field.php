<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\SocialLinksFieldPlayground;

return new ComponentDefinition(
    id: 'social-links-field',
    kind: ComponentKind::Field,
    styles: ['social-links-field'],
    stylesFrom: ['text-input-presentation'],
    uses: ['select-field'],
    entry: 'social-links-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.social-links-field.label',
        description: 'filament-flex-fields::catalog.components.social-links-field.description',
        category: CatalogCategory::TextInput,
        keywords: ['social', 'links', 'field', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-at',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/social-links-field/usage.php'),
        ],
        demoClass: SocialLinksFieldPlayground::class,
        demoSlug: 'social-links-field',
        sort: 347,
        documentation: 'social-links-field.md',
    ),
);
