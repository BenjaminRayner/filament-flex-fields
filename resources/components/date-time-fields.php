<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTimeFieldPlayground;

return new ComponentDefinition(
    id: 'date-time-fields', kind: ComponentKind::Field,
    uses: ['flex-date-picker', 'flex-time-picker', 'flex-duration', 'timezone-field', 'schedule-field'],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.date-time-fields.label',
        description: 'filament-flex-fields::catalog.components.date-time-fields.description',
        category: CatalogCategory::DateAndTime,
        keywords: ['date', 'time', 'fields', 'calendar', 'schedule', 'data', 'czas'],
        icon: 'hugeicons-calendar-clock',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/date-time-fields/selection.php', 'Choose the right control'),
            CodeExample::php(dirname(__DIR__).'/examples/date-time-fields/date-and-time.php', 'Date and time together'),
            CodeExample::php(dirname(__DIR__).'/examples/date-time-fields/periods.php', 'Periods and elapsed time'),
            CodeExample::php(dirname(__DIR__).'/examples/date-time-fields/ranges-and-locale.php', 'Ranges and regional formats'),
            CodeExample::php(dirname(__DIR__).'/examples/date-time-fields/validation.php', 'Try real validation'),
            CodeExample::php(dirname(__DIR__).'/examples/date-time-fields/presentation.php', 'Presentation and field states'),
            CodeExample::php(dirname(__DIR__).'/examples/date-time-fields/recurring.php', 'Timezones and recurring availability'),
        ],
        demoClass: DateTimeFieldPlayground::class,
        demoSlug: 'date-time-fields',
        sort: 270,
        documentation: 'date-and-time-fields.md',
    ),
);
