<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\DateTime;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexYearPicker;

final class PeriodPickerPlayground extends DateTimePlayground
{
    protected function slug(): string
    {
        return 'date-periods';
    }

    /** @return list<ExampleGroup> */
    protected function groups(): array
    {
        return [
            new ExampleGroup('Months', 'Select a month, restrict the year or change the display language. Stored values remain YYYY-MM.', [
                FlexMonthPicker::make('month')->label('Month')->displayFormat('F Y')->locale('en')->default('2026-10'),
                FlexMonthPicker::make('bounded_month')->label('Months in 2026')->minValue('2026-01')->maxValue('2026-12')->default('2026-10'),
                FlexMonthPicker::make('localized_month')->label('Polski')->locale('pl')->displayFormat('F Y')->default('2026-10'),
            ], columns: 3),
            new ExampleGroup('Years', 'Year selection, limits and compact presentation without a day or month input.', [
                FlexYearPicker::make('year')->label('Year')->minValue('2020')->maxValue('2030')->default('2026'),
                FlexYearPicker::make('year_future')->label('Planning year')->minValue('2026')->maxValue('2040')->default('2028'),
                FlexYearPicker::make('year_small')->label('Small · secondary')->size('sm')->variant('secondary')->default('2026'),
            ], columns: 3),
            new ExampleGroup('Elapsed duration', 'Durations may exceed 24 hours. Step buttons change the amount by the configured seconds.', [
                FlexDurationField::make('duration')->label('Beyond 24 hours')->durationStep(900)->minValue('00:15')->maxValue('100:00')->default('26:30')->helperText('15-minute step buttons; maximum 100 hours.'),
                FlexDurationField::make('seconds')->label('Duration with seconds')->showSeconds()->durationStep(5)->default('02:30:15')->helperText('Five-second step buttons.'),
                FlexDurationField::make('short')->label('Meeting duration')->durationStep(300)->minValue('00:05')->maxValue('04:00')->default('00:45')->helperText('Between five minutes and four hours.'),
            ], columns: 3),
            new ExampleGroup('Validation: required and period limits', 'Month/year validation uses their configured storage format and boundaries.', [
                new ValidationExample(FlexMonthPicker::make('required_month')->label('Required month')->required(), invalid: null, valid: '2026-10', description: 'A month must be selected.'),
                new ValidationExample(FlexMonthPicker::make('validation_month')->label('Bounded month')->minValue('2026-01')->maxValue('2026-12')->default('2026-10'), invalid: '2027-01', valid: '2026-10', description: 'January 2027 is outside the allowed year.'),
                new ValidationExample(FlexYearPicker::make('validation_year')->label('Bounded year')->minValue('2020')->maxValue('2030')->default('2026'), invalid: '2031', valid: '2026', description: '2031 exceeds the configured maximum.'),
            ], columns: 3),
            new ExampleGroup('Validation: duration rules', 'Required, maximum and malformed-minute checks use the actual duration validator.', [
                new ValidationExample(FlexDurationField::make('required')->label('Required duration')->required(), invalid: null, valid: '01:30', description: 'Empty duration is rejected.'),
                new ValidationExample(FlexDurationField::make('validation_max')->label('Maximum four hours')->minValue('00:05')->maxValue('04:00')->default('00:45'), invalid: '04:01', valid: '00:45', description: 'Four hours and one minute exceeds the maximum.'),
                new ValidationExample(FlexDurationField::make('validation_minutes')->label('Invalid minutes')->default('01:30'), invalid: '01:75', valid: '01:30', description: '75 is not a valid minute segment.'),
            ], columns: 3),
            new ExampleGroup('Locked states', 'Read-only periods and disabled durations preserve a visible value.', [
                FlexMonthPicker::make('read_only_month')->label('Read-only month')->readOnly()->default('2026-10'),
                FlexYearPicker::make('disabled_year')->label('Disabled year')->disabled()->default('2026'),
                FlexDurationField::make('disabled_duration')->label('Disabled duration')->disabled()->default('26:30'),
            ], columns: 3),
        ];
    }
}
