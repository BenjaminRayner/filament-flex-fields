<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-license-guide', kind: ComponentKind::Display,
    styles: ['flex-license-guide'], stylesFrom: ['flex-catalog-layout'], uses: ['choice-checkbox-cards'],
);
