<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(id: 'file-upload-viewer', kind: ComponentKind::Display, styles: ['file-upload-viewer'], stylesFrom: ['flex-file-upload'], entry: 'file-upload-viewer', uses: ['media-os']);
