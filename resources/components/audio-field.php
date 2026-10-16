<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\AudioFieldPlayground;

return new ComponentDefinition(
    id: 'audio-field',
    kind: ComponentKind::Field,
    styles: ['audio-field'],
    stylesFrom: [],
    entry: 'audio-field',
    capabilities: ['transcription' => ['switch']],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.audio-field.label',
        description: 'filament-flex-fields::catalog.components.audio-field.description',
        category: CatalogCategory::Media,
        keywords: ['audio', 'field'],
        icon: 'hugeicons-audio-waveform',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/audio-field/usage.php'),
        ],
        demoClass: AudioFieldPlayground::class,
        demoSlug: 'audio-field',
        sort: 330,
        documentation: 'audiofield.md',
    ),
);
