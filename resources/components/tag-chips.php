<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'tag-chips',
    kind: ComponentKind::Primitive,
    styles: ['tag-chips'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
);
