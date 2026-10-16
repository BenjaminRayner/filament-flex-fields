<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;

return new ComponentDefinition(
    id: 'flex-file-upload',
    kind: ComponentKind::Field,
    styles: ['flex-file-upload'],
    stylesFrom: ['camera-surface', 'flex-text-input'],
    entry: 'flex-file-upload',
    uses: ['media-os'],
    capabilities: ['sources' => ['segment-control'], 'metadata-translations' => ['translatable-fields']],
    publications: [new PublishedResource('file-icons', 'resources/dist/assets/file-upload/icons', 'filament-flex-fields-assets/file-upload/icons')],
);
