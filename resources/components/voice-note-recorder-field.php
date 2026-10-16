<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\VoiceNoteRecorderFieldPlayground;

return new ComponentDefinition(
    id: 'voice-note-recorder-field',
    kind: ComponentKind::Field,
    styles: ['voice-note-recorder-field'],
    stylesFrom: ['audio-field'],
    uses: ['media-os'],
    entry: 'voice-note-recorder-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.voice-note-recorder-field.label',
        description: 'filament-flex-fields::catalog.components.voice-note-recorder-field.description',
        category: CatalogCategory::Media,
        keywords: ['voice', 'note', 'recorder', 'field'],
        icon: 'hugeicons-ai-concert',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/voice-note-recorder-field/usage.php'),
        ],
        demoClass: VoiceNoteRecorderFieldPlayground::class,
        demoSlug: 'voice-note-recorder-field',
        sort: 331,
        documentation: 'voicenoterecorderfield.md',
    ),
);
