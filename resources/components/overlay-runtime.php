<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'overlay-runtime',
    kind: ComponentKind::Primitive,
    styles: ['overlay-runtime'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
);
