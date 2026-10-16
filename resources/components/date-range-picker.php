<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\RangePickerPlayground;

return new ComponentDefinition(
    id: 'date-range-picker', kind: ComponentKind::Field,
    uses: ['flex-date-picker'],
    catalog: new ComponentPresentation(
        label: 'Date range picker', description: 'Date ranges with custom shortcuts and optional start/end times.',
        category: CatalogCategory::DateAndTime,
        keywords: ['date', 'time', 'calendar'], icon: 'hugeicons-calendar-sync', author: 'FLEX', official: true,
        demoClass: RangePickerPlayground::class, demoSlug: 'date-range-picker', documentation: 'date-range-picker.md', sort: 272,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/date-range-picker/shortcuts.php', 'Built-in, hidden and custom shortcuts'),
            CodeExample::php(dirname(__DIR__).'/examples/date-range-picker/layout.php', 'Layouts and entry'),
            CodeExample::php(dirname(__DIR__).'/examples/date-range-picker/timed-ranges.php', 'Timed ranges'),
            CodeExample::php(dirname(__DIR__).'/examples/date-range-picker/availability.php', 'Availability and same-day policy'),
            CodeExample::php(dirname(__DIR__).'/examples/date-range-picker/validation.php', 'Validation: required, order and completeness'),
            CodeExample::php(dirname(__DIR__).'/examples/date-range-picker/validation-limits.php', 'Validation: bounds, same-day and endpoint times'),
            CodeExample::php(dirname(__DIR__).'/examples/date-range-picker/localization.php', 'Language and width stress cases'),
            CodeExample::php(dirname(__DIR__).'/examples/date-range-picker/presentation.php', 'Sizes, variants and locked values'),
        ],
    ),
);
