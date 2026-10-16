<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-verification-code',
    kind: ComponentKind::Field,
    styles: ['flex-verification-code'],
    stylesFrom: [],
    entry: 'flex-verification-code',
    capabilities: [],
);
