<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\TimePickerPlayground;

return new ComponentDefinition(
    id: 'time-picker', kind: ComponentKind::Field,
    uses: ['flex-time-picker'],
    catalog: new ComponentPresentation(
        label: 'Time picker', description: '12/24-hour clocks, appointment slots and ordered time ranges.',
        category: CatalogCategory::DateAndTime,
        keywords: ['date', 'time', 'calendar'], icon: 'hugeicons-time-quarter-02', author: 'FLEX', official: true,
        demoClass: TimePickerPlayground::class, demoSlug: 'time-picker', documentation: 'time-picker.md', sort: 273,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/time-picker/clocks.php', 'Hour cycles and precision'),
            CodeExample::php(dirname(__DIR__).'/examples/time-picker/availability.php', 'Intervals, allowed times and empty availability'),
            CodeExample::php(dirname(__DIR__).'/examples/time-picker/entry.php', 'Typing, clearing and timezone labels'),
            CodeExample::php(dirname(__DIR__).'/examples/time-picker/time-ranges.php', 'Time ranges'),
            CodeExample::php(dirname(__DIR__).'/examples/time-picker/validation.php', 'Validation: required, bounds and whitelist'),
            CodeExample::php(dirname(__DIR__).'/examples/time-picker/range-validation.php', 'Validation: malformed clocks and range endpoints'),
            CodeExample::php(dirname(__DIR__).'/examples/time-picker/presentation.php', 'Sizes and variants'),
            CodeExample::php(dirname(__DIR__).'/examples/time-picker/states.php', 'Direction and locked states'),
        ],
    ),
);
