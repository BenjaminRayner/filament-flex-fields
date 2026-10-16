<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\PeriodPickerPlayground;

return new ComponentDefinition(
    id: 'date-periods', kind: ComponentKind::Field,
    uses: ['flex-date-picker', 'flex-duration'],
    catalog: new ComponentPresentation(
        label: 'Months, years & duration', description: 'Month and year selection, plus elapsed time beyond 24 hours.',
        category: CatalogCategory::DateAndTime,
        keywords: ['date', 'time', 'calendar'], icon: 'hugeicons-calendar-03', author: 'FLEX', official: true,
        demoClass: PeriodPickerPlayground::class, demoSlug: 'date-periods', documentation: 'date-periods-and-duration.md', sort: 274,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/date-periods/months.php', 'Months'),
            CodeExample::php(dirname(__DIR__).'/examples/date-periods/years.php', 'Years'),
            CodeExample::php(dirname(__DIR__).'/examples/date-periods/durations.php', 'Elapsed duration'),
            CodeExample::php(dirname(__DIR__).'/examples/date-periods/period-validation.php', 'Validation: required and period limits'),
            CodeExample::php(dirname(__DIR__).'/examples/date-periods/duration-validation.php', 'Validation: duration rules'),
            CodeExample::php(dirname(__DIR__).'/examples/date-periods/states.php', 'Locked states'),
        ],
    ),
);
