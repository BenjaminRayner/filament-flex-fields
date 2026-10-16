<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-time-segments',
    kind: ComponentKind::Field,
    styles: ['flex-time-segments'],
    stylesFrom: ['flex-text-input', 'teleported-menu'],
    entry: 'flex-time-segments',
    capabilities: [],
);
