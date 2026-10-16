<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;

return new ComponentDefinition(
    id: 'country-flags',
    kind: ComponentKind::Primitive,
    publications: [new PublishedResource(
        id: 'flags',
        sourceDirectory: 'resources/dist/assets/country-flags',
        publicDirectory: 'filament-flex-fields-assets/country-flags',
    )],
);
