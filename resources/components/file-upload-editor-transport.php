<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'file-upload-editor-transport',
    kind: ComponentKind::Field,
    styles: ['file-upload-image-editor'],
    stylesFrom: [],
    entry: 'file-upload-editor-transport',
);
