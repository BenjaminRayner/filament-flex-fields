<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Carbon\Carbon;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

return [
    FlexDatePicker::make('date')
        ->label('Calendar selection')
        ->clearable()
        ->default('2026-10-15')
        ->helperText('Typing is disabled; clicking the whole input opens the calendar.'),
    FlexDateField::make('typed')
        ->label('Guided numeric date')
        ->displayFormat('d/m/Y')
        ->default('2026-10-15')
        ->helperText('Arrow keys adjust a segment; letters cannot become date values.'),
    FlexDatePicker::make('editable')
        ->label('Calendar + guided entry')
        ->editable()
        ->displayFormat('d/m/Y')
        ->default('2026-10-15')
        ->helperText('Use either the segments or the calendar.'),
    FlexDatePicker::make('bounded')
        ->label('October only')
        ->minDate('2026-10-01')
        ->maxDate('2026-10-31')
        ->default('2026-10-15')
        ->helperText('Dates outside October 2026 are disabled.'),
    FlexDatePicker::make('business_days')
        ->label('Weekdays and blackouts')
        ->disabledWeekdays([0, 6])
        ->disabledDates(['2026-10-16', '2026-10-23'])
        ->default('2026-10-15')
        ->helperText('Saturday, Sunday, October 16 and October 23 are unavailable.'),
    FlexDatePicker::make('availability')
        ->label('Custom availability')
        ->isDateUnavailable(static fn (Carbon $date): bool => $date->isFriday())
        ->default('2026-10-15')
        ->helperText('The PHP callback blocks every Friday.'),
    FlexDatePicker::make('inline')
        ->label('Inline calendar')
        ->inline()
        ->default('2026-10-15')
        ->helperText('Always visible; no dropdown is needed.'),
    FlexDatePicker::make('compact')
        ->label('Compact month')
        ->fixedWeeks(false)
        ->outsideDays(false)
        ->highlightToday(false)
        ->default('2026-10-15')
        ->helperText('No extra fixed rows or days from neighboring months.'),
    FlexDatePicker::make('stay_open')
        ->label('Keep calendar open')
        ->closeOnSelect(false)
        ->default('2026-10-15')
        ->helperText('Choose another day without opening the menu again.'),
    FlexDateTimePicker::make('datetime')
        ->label('Date and time · 24h')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default('2026-10-15T14:30:00'),
    FlexDateTimePicker::make('datetime12')
        ->label('Date and time · 12h')
        ->hourCycle(12)
        ->default('2026-10-15T14:30:00'),
    FlexDateTimePicker::make('seconds')
        ->label('Date and time with seconds')
        ->hourCycle(24)
        ->hideTimeZone()
        ->showSeconds()
        ->secondStep(5)
        ->default('2026-10-15T14:30:15'),
    FlexDatePicker::make('arrival')
        ->label('Arrival')
        ->live()
        ->default('2026-10-10')
        ->helperText('Changing arrival updates the minimum departure date.'),
    FlexDatePicker::make('departure')
        ->label('Departure')
        ->minDate(fn (Get $get): ?string => $get('arrival'))
        ->default('2026-10-15')
        ->helperText('Departure cannot precede the selected arrival.'),
    FlexDateTimePicker::make('slots')
        ->label('Only appointment times')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->defaultTime('09:15')
        ->disabledWeekdays([0, 6])
        ->default('2026-10-15T11:40:00')
        ->helperText('Weekdays at 09:15, 11:40 or 15:10.'),
    FlexDatePicker::make('required')
        ->label('Required appointment')
        ->required()
        ->validationMessages(['required' => 'Choose an appointment date.']),
    FlexDatePicker::make('validation_bounds')
        ->label('Limited appointment')
        ->minDate('2026-10-01')
        ->maxDate('2026-10-31')
        ->default('2026-10-15'),
    FlexDatePicker::make('validation_blackout')
        ->label('Blackout validation')
        ->disabledDates(['2026-10-16'])
        ->disabledWeekdays([0, 6])
        ->default('2026-10-15'),
    FlexDatePicker::make('validation_impossible')
        ->label('Impossible date')
        ->editable()
        ->displayFormat('d/m/Y')
        ->default('2026-10-15'),
    FlexDateTimePicker::make('validation_slots')
        ->label('Unavailable appointment time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->default('2026-10-15T11:40:00'),
    FlexDatePicker::make('validation_custom')
        ->label('Your own rule')
        ->rule('bail')
        ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
            if ((int) substr((string) $value, -2) % 2 !== 0) {
                $fail('Choose an even day of the month.');
            }
        })->default('2026-10-14'),
    FlexDatePicker::make('polish')
        ->label('Polski · Monday first')
        ->locale('pl')
        ->displayFormat('d F Y')
        ->weekStartsOn(1)
        ->default('2026-10-15'),
    FlexDatePicker::make('sunday')
        ->label('English · Sunday first')
        ->locale('en-US')
        ->weekStartsOn(0)
        ->default('2026-10-15'),
    FlexDatePicker::make('rtl')
        ->label('Arabic · RTL')
        ->locale('ar')
        ->calendarSystem('gregory')
        ->default('2026-10-15'),
    FlexDatePicker::make('small')
        ->label('Small · primary')
        ->size('sm')
        ->default('2026-10-15'),
    FlexDatePicker::make('medium')
        ->label('Medium · secondary')
        ->size('md')
        ->variant('secondary')
        ->default('2026-10-15'),
    FlexDatePicker::make('large')
        ->label('Large · flat')
        ->size('lg')
        ->variant('flat')
        ->default('2026-10-15'),
    FlexDatePicker::make('empty')
        ->label('Optional empty date')
        ->placeholder('Choose a date'),
    FlexDatePicker::make('read_only')
        ->label('Read-only date')
        ->readOnly()
        ->default('2026-10-15'),
    FlexDatePicker::make('disabled')
        ->label('Disabled date')
        ->disabled()
        ->default('2026-10-15'),
    FlexDatePicker::make('event_date')
        ->label('Date with onChange')
        ->live()
        ->onChange(static fn (Set $set, ?string $state) => $set('event_value', $state ?? 'No date selected'))
        ->default('2026-10-15'),
    FlexTextInput::make('event_value')
        ->label('Last selected ISO value')
        ->readOnly()
        ->dehydrated(false)
        ->default('2026-10-15'),
];
