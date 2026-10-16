<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexVerificationCodePlayground;

return new ComponentDefinition(
    id: 'verification-code', kind: ComponentKind::Field,
    uses: ['flex-verification-code'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.verification-code.label',
        description: 'filament-flex-fields::catalog.components.verification-code.description',
        category: CatalogCategory::TextInput,
        keywords: ['verification', 'code', 'input', 'text', 'validation', 'tekst'],
        icon: 'hugeicons-shield-key',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/verification-code/usage.php'),
        ],
        demoClass: FlexVerificationCodePlayground::class,
        demoSlug: 'verification-code',
        sort: 370,
        documentation: 'flexverificationcode.md',
    ),
);
