<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-duration',
    kind: ComponentKind::Field,
    stylesFrom: ['flex-text-input'],
    entry: 'flex-duration',
);
