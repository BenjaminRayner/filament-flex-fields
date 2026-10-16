<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

// Importable browser services, with no presentation/CSS or evaluation-time side effects.
return new ComponentDefinition(id: 'media-os', kind: ComponentKind::Primitive, entry: 'media-os');
