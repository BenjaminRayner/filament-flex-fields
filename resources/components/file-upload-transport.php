<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'file-upload-transport',
    kind: ComponentKind::Primitive,
    entry: 'file-upload-transport',
);
