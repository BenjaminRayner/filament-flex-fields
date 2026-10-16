<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'matrix-choice-field',
    kind: ComponentKind::Field,
    styles: ['matrix-choice-field'],
    stylesFrom: [],
    entry: 'matrix-choice-field',
    capabilities: [],
);
