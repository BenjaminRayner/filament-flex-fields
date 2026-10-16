<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\DateTime;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;

final class RangePickerPlayground extends DateTimePlayground
{
    protected function slug(): string
    {
        return 'date-range-picker';
    }

    /** @return list<ExampleGroup> */
    protected function groups(): array
    {
        return [
            new ExampleGroup('Built-in, hidden and custom shortcuts', 'The left shortcuts panel is optional. Supply your own labels and endpoints, or remove it completely.', [
                FlexDateRangeField::make('range')->label('Built-in reporting periods')->default(['start' => '2026-10-10', 'end' => '2026-10-15'])->helperText('Today, Yesterday, Last 7 days and the other built-in periods.'),
                FlexDateRangeField::make('no_presets')->label('No shortcuts')->rangePresets([])->default(['start' => '2026-10-10', 'end' => '2026-10-15'])->helperText('The left panel is completely removed.'),
                FlexDateRangeField::make('custom_presets')->label('Custom periods')->rangePresets([['label' => 'Autumn campaign', 'start' => '2026-10-01', 'end' => '2026-10-31'], ['label' => 'Launch week', 'start' => '2026-10-12', 'end' => '2026-10-18']])->default(['start' => '2026-10-10', 'end' => '2026-10-15'])->helperText('Only Autumn campaign and Launch week appear.'),
            ], columns: 3),
            new ExampleGroup('Layouts and entry', 'Choose one/two months and whether users can type a guided date pattern. Narrow viewports use the compact calendar.', [
                FlexDateRangeField::make('one_month')->label('One month')->calendarMonths(1)->clearable()->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
                FlexDateRangeField::make('two_months')->label('Two months')->calendarMonths(2)->rangePresets([])->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
                FlexDateRangeField::make('editable')->label('Guided range entry')->editable()->displayFormat('d/m/Y')->rangePresets([])->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
            ], columns: 3),
            new ExampleGroup('Timed ranges', 'Independent endpoint times, second precision and explicit time lists are supported on the same range surface.', [
                FlexDateRangeField::make('timed')->label('Range with time · 12h')->granularity('minute')->hourCycle(12)->default(['start' => '2026-10-10T08:45:00', 'end' => '2026-10-15T18:45:00']),
                FlexDateRangeField::make('timed24')->label('Range with time · 24h')->granularity('minute')->hourCycle(24)->hideTimeZone()->rangePresets([])->allowedTimes(['09:00', '12:00', '17:00'])->default(['start' => '2026-10-10T09:00:00', 'end' => '2026-10-15T17:00:00']),
                FlexDateRangeField::make('seconds')->label('Timed range with seconds')->granularity('second')->hourCycle(24)->hideTimeZone()->rangePresets([])->default(['start' => '2026-10-10T09:00:15', 'end' => '2026-10-15T17:00:30']),
            ], columns: 3),
            new ExampleGroup('Availability and same-day policy', 'Day restrictions apply to each endpoint. Applications can add their own rules when every interior day must be available.', [
                FlexDateRangeField::make('availability')->label('Working-day endpoints')->disabledWeekdays([0, 6])->disabledDates(['2026-10-16'])->rangePresets([])->default(['start' => '2026-10-12', 'end' => '2026-10-15'])->helperText('Weekend endpoints and October 16 are blocked.'),
                FlexDateRangeField::make('same_day')->label('Same day allowed')->allowSameDay()->rangePresets([])->default(['start' => '2026-10-15', 'end' => '2026-10-15']),
                FlexDateRangeField::make('dynamic_presets')->label('Dynamic shortcuts')->rangePresets(static fn (): array => [['label' => 'Current month', 'start' => now()->startOfMonth()->format('Y-m-d'), 'end' => now()->endOfMonth()->format('Y-m-d')]])->default(['start' => '2026-10-10', 'end' => '2026-10-15'])->helperText('The PHP callback resolves this month when the field is built.'),
            ], columns: 3),
            new ExampleGroup('Validation: required, order and completeness', 'Try the rejected examples to see the actual messages for empty, reversed and incomplete ranges.', [
                new ValidationExample(FlexDateRangeField::make('required')->label('Required date range')->required()->rangePresets([]), invalid: null, valid: ['start' => '2026-10-10', 'end' => '2026-10-15'], description: 'Both endpoints are required.'),
                new ValidationExample(FlexDateRangeField::make('validation_order')->label('Reversed range')->rangePresets([])->default(['start' => '2026-10-10', 'end' => '2026-10-15']), invalid: ['start' => '2026-10-15', 'end' => '2026-10-10'], valid: ['start' => '2026-10-10', 'end' => '2026-10-15'], description: 'The end cannot precede the start.'),
                new ValidationExample(FlexDateRangeField::make('validation_partial')->label('Incomplete range')->rangePresets([])->default(['start' => '2026-10-10', 'end' => '2026-10-15']), invalid: ['start' => '2026-10-10', 'end' => null], valid: ['start' => '2026-10-10', 'end' => '2026-10-15'], description: 'A start without an end is rejected.'),
            ], columns: 3),
            new ExampleGroup('Validation: bounds, same-day and endpoint times', 'Limits are enforced on submission as well as in the menu.', [
                new ValidationExample(FlexDateRangeField::make('bounded')->label('October booking')->minDate('2026-10-01')->maxDate('2026-10-31')->rangePresets([])->default(['start' => '2026-10-10', 'end' => '2026-10-15']), invalid: ['start' => '2026-10-10', 'end' => '2026-11-01'], valid: ['start' => '2026-10-10', 'end' => '2026-10-15'], description: 'The end must also fit inside October.'),
                new ValidationExample(FlexDateRangeField::make('validation_same_day')->label('At least two calendar days')->allowSameDay(false)->rangePresets([])->default(['start' => '2026-10-10', 'end' => '2026-10-15']), invalid: ['start' => '2026-10-15', 'end' => '2026-10-15'], valid: ['start' => '2026-10-10', 'end' => '2026-10-15'], description: 'Selecting the same day twice is rejected.'),
                new ValidationExample(FlexDateRangeField::make('validation_time')->label('Restricted endpoint times')->granularity('minute')->hourCycle(24)->hideTimeZone()->rangePresets([])->allowedTimes(['09:00', '12:00', '17:00'])->default(['start' => '2026-10-10T09:00:00', 'end' => '2026-10-15T17:00:00']), invalid: ['start' => '2026-10-10T10:00:00', 'end' => '2026-10-15T17:00:00'], valid: ['start' => '2026-10-10T09:00:00', 'end' => '2026-10-15T17:00:00'], description: '10:00 is unavailable even though the date is valid.'),
            ], columns: 3),
            new ExampleGroup('Language and width stress cases', 'Long display text remains contained inside the input. Open the range to see the complete endpoint summary.', [
                FlexDateRangeField::make('long_text')->label('Long date labels')->displayFormat('l, F j, Y')->rangePresets([])->default(['start' => '2026-10-10', 'end' => '2026-10-15'])->helperText('Try this input at small viewport widths.'),
                FlexDateRangeField::make('polish')->label('Polski · Monday first')->locale('pl')->displayFormat('d F Y')->weekStartsOn(1)->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
                FlexDateRangeField::make('rtl')->label('Arabic · RTL')->locale('ar')->weekStartsOn(0)->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
            ], columns: 3),
            new ExampleGroup('Sizes, variants and locked values', 'Compare compact input sizing and read-only behavior without changing the range semantics.', [
                FlexDateRangeField::make('small')->label('Small · secondary')->size('sm')->variant('secondary')->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
                FlexDateRangeField::make('large')->label('Large · flat')->size('lg')->variant('flat')->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
                FlexDateRangeField::make('read_only')->label('Read-only range')->readOnly()->default(['start' => '2026-10-10', 'end' => '2026-10-15']),
            ], columns: 3),
        ];
    }
}
