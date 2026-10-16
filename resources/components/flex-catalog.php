<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-catalog', kind: ComponentKind::Display,
    stylesFrom: ['flex-catalog-layout', 'flex-text-input'], uses: ['select-field'], entry: 'flex-catalog',
);
