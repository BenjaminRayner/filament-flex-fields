<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'timezone-picker-presentation',
    kind: ComponentKind::Primitive,
    styles: ['timezone-field'],
    stylesFrom: ['text-input-presentation', 'teleported-menu'],
);
