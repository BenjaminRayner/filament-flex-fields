<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(id: 'flex-code-preview', kind: ComponentKind::Display, styles: ['flex-code-preview'], stylesFrom: ['flex-catalog-layout'], entry: 'flex-code-preview');
