<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\DateTimePlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\ExampleGroup;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\ValidationExample;

final class TimezoneFieldPlayground extends DateTimePlayground
{
    protected function slug(): string
    {
        return 'timezone-field';
    }

    /** @return list<ExampleGroup> */
    protected function groups(): array
    {
        return [
            new ExampleGroup('Search and browser defaults', 'Timezone values are IANA identifiers. Search by region/city or let the browser choose an initial timezone for an empty field.', [
                TimezoneField::make('timezone__basic')->label('Search all timezones')->defaultTimezone('Europe/Warsaw')->default('Europe/Warsaw')->helperText('Stores Europe/Warsaw; offsets are shown in the menu.'),
                TimezoneField::make('timezone__empty')->label('Optional timezone')->helperText('Empty until a timezone is selected.'),
                TimezoneField::make('timezone__browser')->label('Browser timezone')->browserTimezoneDefault()->browserTimezoneSortFirst()->helperText('The browser timezone is preferred only while empty.'),
            ], columns: 3),
            new ExampleGroup('Restricted and excluded timezones', 'Use a whitelist, exclude individual identifiers or hide UTC from the catalog.', [
                TimezoneField::make('timezone__limited')->label('Approved timezones')->timezones(['Europe/Warsaw', 'Europe/Berlin', 'America/New_York', 'UTC'])->default('America/New_York')->helperText('Only these four identifiers are offered.'),
                TimezoneField::make('timezone__excluded')->label('Exclude selected timezones')->exceptTimezones(['America/New_York', 'Asia/Tokyo'])->default('Europe/Warsaw')->helperText('New York and Tokyo are removed.'),
                TimezoneField::make('timezone__no_utc')->label('Without UTC')->includeUtc(false)->default('Europe/Warsaw')->helperText('Canonical UTC is omitted.'),
            ], columns: 3),
            new ExampleGroup('Labels and sizes', 'Hide offsets, localize labels or use a larger input. The dropdown remains the shared FLEX Select.', [
                TimezoneField::make('timezone__no_offset')->label('Without offset label')->showOffset(false)->default('Europe/Warsaw'),
                TimezoneField::make('timezone__localized')->label('Polski')->locale('pl')->default('Europe/Warsaw'),
                TimezoneField::make('timezone__lg')->label('Large timezone input')->size('lg')->default('Asia/Tokyo'),
            ], columns: 3),
            new ExampleGroup('Validation: required and whitelist', 'These checks reject empty and unsupported values independently from the other examples.', [
                new ValidationExample(TimezoneField::make('timezone__required')->label('Required timezone')->required()->validationMessages(['required' => 'Choose a timezone.']), invalid: null, valid: 'Europe/Warsaw', description: 'Empty value is rejected.'),
                new ValidationExample(TimezoneField::make('timezone__validation_list')->label('Restricted timezone validation')->timezones(['Europe/Warsaw', 'UTC'])->in(['Europe/Warsaw', 'UTC'])->default('Europe/Warsaw'), invalid: 'Asia/Tokyo', valid: 'Europe/Warsaw', description: 'Asia/Tokyo is outside this explicit server whitelist.'),
                new ValidationExample(TimezoneField::make('timezone__validation_identifier')->label('IANA identifier validation')->rules(['timezone'])->default('Europe/Warsaw'), invalid: 'Mars/Olympus', valid: 'Europe/Warsaw', description: 'A made-up timezone identifier is rejected.'),
            ], columns: 3),
            new ExampleGroup('Variants and locked values', 'Compare a secondary input, a read-only timezone and a disabled timezone.', [
                TimezoneField::make('timezone__secondary')->label('Small · secondary')->size('sm')->variant('secondary')->default('Europe/Warsaw'),
                TimezoneField::make('timezone__read_only')->label('Read-only timezone')->readOnly()->default('Europe/London'),
                TimezoneField::make('timezone__disabled')->label('Disabled timezone')->disabled()->default('Europe/London'),
            ], columns: 3),
        ];
    }
}
