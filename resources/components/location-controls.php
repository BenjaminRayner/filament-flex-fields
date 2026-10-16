<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'location-controls',
    kind: ComponentKind::Primitive,
    styles: ['location-controls'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
);
