<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;

return new ComponentDefinition(
    id: 'emoji-picker',
    kind: ComponentKind::Field,
    styles: ['emoji-picker'],
    stylesFrom: ['teleported-menu', 'text-input-presentation'],
    entry: null,
    capabilities: [],
    publications: [new PublishedResource(
        id: 'shadow-styles', sourceDirectory: 'resources/dist/assets/emoji-picker', publicDirectory: 'filament-flex-fields-assets/emoji-picker',
    )],
);
