<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'item-card',
    kind: ComponentKind::Display,
    styles: ['item-card'],
    stylesFrom: [],
    entry: null,
    capabilities: ['pressable' => ['item-card-interaction']],
);
