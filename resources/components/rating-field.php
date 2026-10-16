<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'rating-field',
    kind: ComponentKind::Field,
    styles: [],
    stylesFrom: ['rating-visuals'],
    entry: 'rating-field',
    capabilities: [],
);
