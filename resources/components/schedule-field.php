<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\ScheduleFieldPlayground;

return new ComponentDefinition(
    id: 'schedule-field',
    kind: ComponentKind::Field,
    styles: ['schedule-field'],
    stylesFrom: ['text-input-presentation'],
    uses: ['timezone-field', 'select-field', 'switch'],
    entry: 'schedule-field',
    capabilities: [],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.schedule-field.label',
        description: 'filament-flex-fields::catalog.components.schedule-field.description',
        category: CatalogCategory::DateAndTime,
        keywords: ['schedule', 'field', 'date', 'time', 'calendar', 'data', 'czas'],
        icon: 'hugeicons-chat-delay',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/schedule-field/opening-hours.php', 'Weekly opening hours'),
            CodeExample::php(dirname(__DIR__).'/examples/schedule-field/shifts.php', 'Split shifts and overnight work'),
            CodeExample::php(dirname(__DIR__).'/examples/schedule-field/policies.php', 'Timezone and copying policy'),
            CodeExample::php(dirname(__DIR__).'/examples/schedule-field/validation.php', 'Validation: overlapping and reversed slots'),
            CodeExample::php(dirname(__DIR__).'/examples/schedule-field/validation-policy.php', 'Validation: required days and slot count'),
            CodeExample::php(dirname(__DIR__).'/examples/schedule-field/presentation.php', 'Sizes and visual variants'),
            CodeExample::php(dirname(__DIR__).'/examples/schedule-field/states.php', 'Locked states'),
            CodeExample::php(dirname(__DIR__).'/examples/schedule-field/displays.php', 'Table column and infolist'),
        ],
        demoClass: ScheduleFieldPlayground::class,
        demoSlug: 'schedule-field',
        sort: 348,
        documentation: 'schedule-field.md',
    ),
);
