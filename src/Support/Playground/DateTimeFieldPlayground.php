<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeRangeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexYearPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\DateTimePlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\ExampleGroup;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\ValidationExample;

final class DateTimeFieldPlayground extends DateTimePlayground
{
    protected function slug(): string
    {
        return 'date-time-fields';
    }

    /** @return list<ExampleGroup> */
    protected function groups(): array
    {
        return [
            new ExampleGroup('Choose the right control', 'Compare a date, a date range and a clock. Open any input to try its keyboard and calendar controls.', [
                FlexDatePicker::make('date_time__date')->label('Date picker')->clearable()->default('2026-10-15')->helperText('Calendar selection; clear the value with ×.'),
                FlexDateRangeField::make('date_time__range')->label('Date range picker')->default(['start' => '2026-10-10', 'end' => '2026-10-15'])->helperText('Choose endpoints; Apply commits, Cancel keeps the previous value.'),
                FlexTimeField::make('date_time__time')->label('Time picker')->hourCycle(24)->hideTimeZone()->default('14:30')->helperText('24-hour display; stored as HH:mm.'),
            ], columns: 3),
            new ExampleGroup('Date and time together', 'Select both a day and a time, including seconds or a list of allowed appointment times.', [
                FlexDateTimePicker::make('date_time__datetime')->label('Date and time · 12h')->hourCycle(12)->default('2026-10-15T14:30:00')->helperText('A calendar with its own time column.'),
                FlexDateTimePicker::make('date_time__datetime_seconds')->label('Date and time with seconds')->hourCycle(24)->hideTimeZone()->showSeconds()->secondStep(5)->default('2026-10-15T14:30:15')->helperText('Second precision in both display and stored value.'),
                FlexDateTimePicker::make('date_time__appointment')->label('Appointment slots')->hourCycle(24)->hideTimeZone()->allowedTimes(['09:15', '11:40', '15:10'])->defaultTime('09:15')->default('2026-10-15T11:40:00')->helperText('Only 09:15, 11:40 and 15:10 can be accepted.'),
            ], columns: 3),
            new ExampleGroup('Periods and elapsed time', 'A month, a year and an elapsed duration have different storage formats and selection controls.', [
                FlexMonthPicker::make('date_time__month')->label('Month')->displayFormat('F Y')->default('2026-10')->helperText('Stores YYYY-MM, without inventing a selected day.'),
                FlexYearPicker::make('date_time__year')->label('Year')->minValue('2020')->maxValue('2030')->default('2026')->helperText('Only 2020–2030.'),
                FlexDurationField::make('date_time__duration')->label('Elapsed duration')->minValue('00:15')->maxValue('100:00')->durationStep(900)->default('26:30')->helperText('More than 24 hours; 15-minute step buttons.'),
            ], columns: 3),
            new ExampleGroup('Ranges and regional formats', 'Compare a timed period, opening hours and a localized date. See the dedicated galleries for more combinations.', [
                FlexDateRangeField::make('date_time__timed_range')->label('Range with time')->granularity('minute')->hourCycle(12)->rangePresets([])->default(['start' => '2026-10-10T08:45:00', 'end' => '2026-10-15T18:45:00'])->helperText('No shortcuts; independent start and end time.'),
                FlexTimeRangeField::make('date_time__time_range')->label('Opening hours')->hourCycle(24)->hideTimeZone()->minValue('08:00')->maxValue('20:00')->default(['start' => '09:00', 'end' => '17:00'])->helperText('Both endpoints must fit within 08:00–20:00.'),
                FlexDatePicker::make('date_time__localized')->label('Localized date')->locale('pl')->displayFormat('d F Y')->weekStartsOn(1)->default('2026-10-15')->helperText('Polish labels; Monday is the first day of the week.'),
            ], columns: 3),
            new ExampleGroup('Try real validation', 'These buttons run the actual server-side field rules. Show error loads a rejected example; Use valid value fixes only that field.', [
                new ValidationExample(FlexDatePicker::make('date_time__required')->label('Required date')->required()->validationMessages(['required' => 'Choose an appointment date.']), invalid: null, valid: '2026-10-15', description: 'An empty value is rejected. Choose a date or use the valid example.'),
                new ValidationExample(FlexDatePicker::make('date_time__bounded')->label('Working-day appointment')->minDate('2026-10-01')->maxDate('2026-10-31')->disabledWeekdays([0, 6])->disabledDates(['2026-10-16'])->default('2026-10-15'), invalid: '2026-10-16', valid: '2026-10-15', description: 'October only; weekends and October 16 are blocked.'),
                new ValidationExample(FlexTimeField::make('date_time__allowed')->label('Restricted time')->hourCycle(24)->hideTimeZone()->allowedTimes(['09:15', '11:40', '15:10'])->default('11:40'), invalid: '12:00', valid: '11:40', description: '12:00 is rejected even if supplied outside the menu.'),
            ], columns: 3),
            new ExampleGroup('Presentation and field states', 'Compare the same input at three sizes. The detailed galleries also include read-only, disabled, empty and invalid states.', [
                FlexDatePicker::make('date_time__small')->label('Small · primary')->size('sm')->default('2026-10-15'),
                FlexDatePicker::make('date_time__secondary')->label('Medium · secondary')->size('md')->variant('secondary')->default('2026-10-15'),
                FlexDatePicker::make('date_time__flat')->label('Large · flat')->size('lg')->variant('flat')->default('2026-10-15'),
            ], columns: 3),
            new ExampleGroup('Timezones and recurring availability', 'Timezone selection and recurring opening hours are part of the collection too. Their galleries cover browser defaults, restrictions, breaks and overnight shifts.', [
                TimezoneField::make('date_time__timezone')->label('Timezone')->defaultTimezone('Europe/Warsaw')->default('Europe/Warsaw')->helperText('Search IANA timezone identifiers using FLEX Select.'),
                ScheduleField::make('date_time__schedule')->label('Recurring availability')->days(['mon', 'tue', 'wed'])->timeStep(15)->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue', 'wed']))->helperText('Enable days, add slots and choose their timezone.'),
            ], columns: 2),
        ];
    }
}
