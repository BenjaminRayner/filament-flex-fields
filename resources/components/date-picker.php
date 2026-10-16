<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\DatePickerPlayground;

return new ComponentDefinition(
    id: 'date-picker', kind: ComponentKind::Field,
    uses: ['flex-date-picker', 'flex-text-input'],
    catalog: new ComponentPresentation(
        label: 'Date picker', description: 'Single dates, guided entry and date/time appointments.',
        category: CatalogCategory::DateAndTime,
        keywords: ['date', 'time', 'calendar'], icon: 'hugeicons-calendar-01', author: 'FLEX', official: true,
        demoClass: DatePickerPlayground::class, demoSlug: 'date-picker', documentation: 'date-picker.md', sort: 271,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/selection.php', 'Selection and guided typing'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/availability.php', 'Availability rules'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/calendar-layout.php', 'Calendar layout'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/date-and-time.php', 'Date and time'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/appointments.php', 'Appointment workflows'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/validation.php', 'Validation: empty, bounds and blackouts'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/custom-validation.php', 'Validation: typed values and custom rules'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/localization.php', 'Language, first weekday and calendar system'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/presentation.php', 'Sizes and visual variants'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/states.php', 'Empty, read-only and disabled'),
            CodeExample::php(dirname(__DIR__).'/examples/date-picker/events.php', 'React to a date change'),
        ],
    ),
);
