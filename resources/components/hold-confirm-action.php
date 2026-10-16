<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'hold-confirm-action',
    kind: ComponentKind::Action,
    styles: ['hold-confirm-action'],
    stylesFrom: [],
    entry: 'hold-confirm-action',
    capabilities: [],
);
