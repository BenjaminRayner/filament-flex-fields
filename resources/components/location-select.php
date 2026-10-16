<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'location-select',
    kind: ComponentKind::Field,
    styles: [],
    stylesFrom: ['flex-text-input', 'location-controls'],
    uses: ['select-field', 'location-geocoding'],
    entry: 'location-select',
    capabilities: [],
);
