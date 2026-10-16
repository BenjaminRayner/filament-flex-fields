<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'flex-date-picker',
    kind: ComponentKind::Field,
    stylesFrom: ['text-input-presentation', 'date-value-presentation'],
    entry: 'flex-date-picker',
    capabilities: ['calendar' => ['date-time-calendar'], 'time-selection' => ['date-time-clock']],
);
