<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'text-input-presentation',
    kind: ComponentKind::Primitive,
    styles: ['flex-text-input'],
);
