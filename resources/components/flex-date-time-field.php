<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-date-time-field',
    kind: ComponentKind::Field,
    styles: ['flex-date-time-field'],
    stylesFrom: ['flex-text-input', 'teleported-menu'],
    entry: 'flex-date-time-field',
    capabilities: [],
);
