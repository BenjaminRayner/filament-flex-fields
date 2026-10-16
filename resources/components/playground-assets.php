<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;

return new ComponentDefinition(
    id: 'playground-assets', kind: ComponentKind::Primitive,
    publications: [new PublishedResource('examples', 'resources/dist/assets/playground', 'filament-flex-fields-assets/playground')],
);
