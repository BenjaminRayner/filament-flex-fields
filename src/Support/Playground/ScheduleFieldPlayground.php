<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\DateTimePlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\ExampleGroup;
use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\ValidationExample;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;

final class ScheduleFieldPlayground extends DateTimePlayground
{
    /** @return list<Component> */
    protected function displayComponents(): array
    {
        return [Section::make('Table column and infolist')
            ->description('Active days have a circular badge with opening hours in its tooltip. The infolist can show the full week or only active days.')
            ->schema([Livewire::make('flex-schedule-display-examples')->key('schedule-displays')])];
    }

    protected function slug(): string
    {
        return 'schedule-field';
    }

    /** @return list<ExampleGroup> */
    protected function groups(): array
    {
        return [
            new ExampleGroup('Weekly opening hours', 'Enable days, add time slots and copy a source day to your configured workdays. The timezone selector uses FLEX Select.', [
                ScheduleField::make('schedule__default')->label('Business hours')->days(['mon', 'tue', 'wed', 'thu', 'fri'])->workdays(['mon', 'tue', 'wed', 'thu', 'fri'])->timeStep(15)->maxSlots(6)->default(ScheduleField::defaultSchedule('Europe/Warsaw', ['mon', 'tue', 'wed', 'thu', 'fri']))->helperText('15-minute choices; weekdays only.'),
                ScheduleField::make('schedule__locked_weekends')->label('Locked weekends')->lockedDays(['sat', 'sun'])->timeStep(30)->default(ScheduleField::defaultSchedule('Europe/Warsaw', ['mon', 'tue', 'wed', 'thu', 'fri']))->helperText('Saturday and Sunday cannot be changed.'),
            ], columns: 2),
            new ExampleGroup('Split shifts and overnight work', 'Breaks remain distinct from working slots. Overnight slots explicitly continue into the following day.', [
                ScheduleField::make('schedule__custom_slots')->label('Split shift with lunch break')->days(['mon'])->timezone('Europe/Warsaw')->maxSlots(6)->default(['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '12:00', 'type' => 'slot'], ['from' => '12:00', 'to' => '13:00', 'type' => 'break'], ['from' => '13:00', 'to' => '17:00', 'type' => 'slot']]]]])->helperText('09:00–12:00, lunch 12:00–13:00, then 13:00–17:00.'),
                ScheduleField::make('schedule__overnight')->label('Overnight shift')->days(['mon'])->timezone('Europe/Warsaw')->allowCopyToWeekdays(false)->default(['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '22:00', 'to' => '06:00', 'overnight' => true, 'type' => 'slot']]]]])->helperText('22:00–06:00, with overnight enabled.'),
            ], columns: 2),
            new ExampleGroup('Timezone and copying policy', 'Hide the timezone entirely or choose your own copy source and target workdays.', [
                ScheduleField::make('schedule__no_timezone')->label('Local schedule')->days(['mon', 'tue', 'wed'])->timezone(null)->allowCopyToWeekdays(false)->default(ScheduleField::defaultSchedule(null, ['mon', 'tue', 'wed']))->helperText('No timezone selector and no copy action.'),
                ScheduleField::make('schedule__copy')->label('Copy Tuesday to workdays')->days(['mon', 'tue', 'wed', 'thu', 'fri'])->copySourceDay('tue')->workdays(['wed', 'thu', 'fri'])->default(ScheduleField::defaultSchedule('Europe/Warsaw', ['mon', 'tue', 'wed', 'thu', 'fri']))->helperText('Tuesday is copied only to Wednesday, Thursday and Friday.'),
            ], columns: 2),
            new ExampleGroup('Validation: overlapping and reversed slots', 'Use Show error to load invalid schedule data and run its real server-side rules.', [
                new ValidationExample(ScheduleField::make('schedule__validation_overlap')->label('Overlapping Monday slots')->days(['mon'])->timezone('Europe/Warsaw')->default(['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']]]]]), invalid: ['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '12:00'], ['from' => '11:00', 'to' => '14:00']]]]], valid: ['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']]]]], description: 'Working slots cannot overlap.'),
                new ValidationExample(ScheduleField::make('schedule__validation_order')->label('Reversed daytime slot')->days(['mon'])->timezone('Europe/Warsaw')->default(['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']]]]]), invalid: ['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '17:00', 'to' => '09:00']]]]], valid: ['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']]]]], description: 'Use an explicit overnight slot when a shift crosses midnight.'),
            ], columns: 2),
            new ExampleGroup('Validation: required days and slot count', 'Required schedules need at least one working slot. Every enabled day obeys its slot-count policy.', [
                new ValidationExample(ScheduleField::make('schedule__required')->label('Required availability')->days(['mon'])->timezone('Europe/Warsaw')->required(), invalid: ['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => false, 'slots' => []]]], valid: ['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']]]]], description: 'A schedule with no enabled working day is rejected.'),
                new ValidationExample(ScheduleField::make('schedule__validation_count')->label('At most two slots')->days(['mon'])->timezone('Europe/Warsaw')->minSlots(1)->maxSlots(2)->default(['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']]]]]), invalid: ['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '12:00', 'type' => 'slot'], ['from' => '12:00', 'to' => '13:00', 'type' => 'break'], ['from' => '13:00', 'to' => '17:00', 'type' => 'slot']]]]], valid: ['timezone' => 'Europe/Warsaw', 'days' => ['mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'slot']]]]], description: 'Three slots, including the break, exceed this limit.'),
            ], columns: 2),
            new ExampleGroup('Sizes and visual variants', 'Compare compact and larger weekly editors.', [
                ScheduleField::make('schedule__sm')->label('Small · primary')->days(['mon', 'tue'])->size('sm')->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue'])),
                ScheduleField::make('schedule__secondary')->label('Large · secondary')->days(['mon', 'tue'])->size('lg')->variant('secondary')->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue'])),
            ], columns: 2),
            new ExampleGroup('Locked states', 'Read-only and disabled schedules stay visible without allowing edits.', [
                ScheduleField::make('schedule__read_only')->label('Read-only schedule')->days(['mon', 'tue'])->readOnly()->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue'])),
                ScheduleField::make('schedule__disabled')->label('Disabled schedule')->days(['mon', 'tue'])->disabled()->default(ScheduleField::defaultSchedule('UTC', ['mon', 'tue'])),
            ], columns: 2),
        ];
    }
}
