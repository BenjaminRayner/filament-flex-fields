<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-catalog-detail', kind: ComponentKind::Display,
    stylesFrom: ['flex-catalog-layout', 'teleported-menu'], entry: 'flex-catalog-detail',
);
