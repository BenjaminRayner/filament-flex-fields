<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'date-time-clock',
    kind: ComponentKind::Primitive,
    styles: ['date-time-clock'],
    stylesFrom: ['text-input-presentation', 'teleported-menu'],
);
