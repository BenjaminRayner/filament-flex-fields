<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'map-picker-dropdown',
    kind: ComponentKind::Primitive,
    styles: ['map-picker-dropdown'],
    stylesFrom: ['teleported-menu', 'select-field'],
    entry: null,
    capabilities: [],
);
