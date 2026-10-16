<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'selection-indicator',
    kind: ComponentKind::Primitive,
    styles: ['selection-indicator'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
);
