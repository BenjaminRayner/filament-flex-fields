<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

// Reuse Select rows and menu chrome without loading its Alpine field controller.
return new ComponentDefinition(
    id: 'text-input-suggestions',
    kind: ComponentKind::Primitive,
    styles: [],
    stylesFrom: ['select-field'],
    entry: null,
);
