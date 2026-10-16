<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'date-time-calendar',
    kind: ComponentKind::Primitive,
    styles: ['date-time-calendar'],
    stylesFrom: ['teleported-menu', 'date-value-presentation'],
);
