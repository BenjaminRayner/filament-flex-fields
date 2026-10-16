<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'date-value-presentation',
    kind: ComponentKind::Primitive,
    styles: ['date-value-presentation'],
);
