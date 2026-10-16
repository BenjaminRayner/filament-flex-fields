<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'user-display',
    kind: ComponentKind::Display,
    styles: ['user-display'],
    stylesFrom: [],
    entry: null,
    capabilities: [],
);
