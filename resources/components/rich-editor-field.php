<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'rich-editor-field',
    kind: ComponentKind::Field,
    styles: ['rich-editor-field'],
    uses: ['media-os'],
    stylesFrom: [],
    entry: 'flex-rich-editor',
    capabilities: [],
);
