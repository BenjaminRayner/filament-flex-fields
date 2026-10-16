<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'teleported-menu',
    kind: ComponentKind::Primitive,
    styles: ['teleported-menu'],
    stylesFrom: ['overlay-runtime'],
    entry: null,
    capabilities: [],
);
