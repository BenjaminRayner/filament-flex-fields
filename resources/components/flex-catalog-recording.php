<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-catalog-recording',
    kind: ComponentKind::Display,
    styles: ['flex-catalog-recording'],
    entry: 'flex-catalog-recording',
);
