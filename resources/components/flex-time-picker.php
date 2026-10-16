<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-time-picker',
    kind: ComponentKind::Field,
    styles: ['flex-time-picker'],
    stylesFrom: ['text-input-presentation', 'date-value-presentation'],
    entry: 'flex-time-picker',
    capabilities: ['menu' => ['date-time-clock']],
);
