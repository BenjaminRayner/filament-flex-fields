<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeRangeField;

return [
    FlexTimeField::make('clock24')
        ->label('Time · 24h')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default('14:30'),
    FlexTimeField::make('clock12')
        ->label('Time · 12h')
        ->hourCycle(12)
        ->hideTimeZone()
        ->default('14:30'),
    FlexTimeField::make('seconds')
        ->label('Time with seconds')
        ->hourCycle(24)
        ->hideTimeZone()
        ->showSeconds()
        ->secondStep(5)
        ->default('14:30:15'),
    FlexTimeField::make('interval')
        ->label('Five-minute menu')
        ->hourCycle(24)
        ->hideTimeZone()
        ->minuteStep(5)
        ->minValue('09:00')
        ->maxValue('18:00')
        ->default('14:30')
        ->helperText('Options from 09:00 to 18:00, every five minutes.'),
    FlexTimeField::make('slots')
        ->label('Only appointment times')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->default('11:40')
        ->helperText('09:15, 11:40 and 15:10 are the only allowed values.'),
    FlexTimeField::make('empty')
        ->label('No available hours')
        ->allowedTimes([])
        ->hideTimeZone()
        ->helperText('Empty state; no time can be selected.'),
    FlexTimeField::make('typed')
        ->label('Guided time entry')
        ->hourCycle(24)
        ->hideTimeZone()
        ->editable()
        ->default('14:30')
        ->helperText('Numeric time segments; ArrowUp/ArrowDown adjust the selected segment.'),
    FlexTimeField::make('optional')
        ->label('Clearable time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->clearable()
        ->default('14:30')
        ->helperText('Use × to clear this optional value.'),
    FlexTimeField::make('zone')
        ->label('Clock with timezone label')
        ->hourCycle(12)
        ->timeZone('Europe/Warsaw')
        ->default('14:30')
        ->helperText('The clock remains a local HH:mm value.'),
    FlexTimeRangeField::make('range')
        ->label('Opening hours')
        ->hourCycle(24)
        ->hideTimeZone()
        ->minValue('08:00')
        ->maxValue('20:00')
        ->default(['start' => '09:00', 'end' => '17:00']),
    FlexTimeRangeField::make('range12')
        ->label('Opening hours · 12h')
        ->hourCycle(12)
        ->hideTimeZone()
        ->default(['start' => '09:00', 'end' => '17:00']),
    FlexTimeRangeField::make('range_slots')
        ->label('Only approved endpoints')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:00', '12:00', '17:00'])
        ->default(['start' => '09:00', 'end' => '17:00']),
    FlexTimeField::make('required')
        ->label('Required time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->required()
        ->validationMessages(['required' => 'Choose a start time.']),
    FlexTimeField::make('validation_bounds')
        ->label('Business-hour limit')
        ->hourCycle(24)
        ->hideTimeZone()
        ->editable()
        ->minValue('09:00')
        ->maxValue('18:00')
        ->default('14:30'),
    FlexTimeField::make('validation_slots')
        ->label('Whitelist validation')
        ->hourCycle(24)
        ->hideTimeZone()
        ->allowedTimes(['09:15', '11:40', '15:10'])
        ->default('11:40'),
    FlexTimeField::make('validation_clock')
        ->label('Malformed time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->editable()
        ->default('14:30'),
    FlexTimeRangeField::make('validation_order')
        ->label('Reversed time range')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default(['start' => '09:00', 'end' => '17:00']),
    FlexTimeRangeField::make('validation_partial')
        ->label('Incomplete time range')
        ->hourCycle(24)
        ->hideTimeZone()
        ->default(['start' => '09:00', 'end' => '17:00']),
    FlexTimeField::make('small')
        ->label('Small · primary')
        ->hourCycle(24)
        ->hideTimeZone()
        ->size('sm')
        ->default('14:30'),
    FlexTimeField::make('medium')
        ->label('Medium · secondary')
        ->hourCycle(24)
        ->hideTimeZone()
        ->size('md')
        ->variant('secondary')
        ->default('14:30'),
    FlexTimeField::make('large')
        ->label('Large · flat')
        ->hourCycle(24)
        ->hideTimeZone()
        ->size('lg')
        ->variant('flat')
        ->default('14:30'),
    FlexTimeField::make('rtl')
        ->label('Arabic · RTL')
        ->locale('ar')
        ->hourCycle(12)
        ->hideTimeZone()
        ->default('14:30'),
    FlexTimeField::make('read_only')
        ->label('Read-only time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->readOnly()
        ->default('14:30'),
    FlexTimeField::make('disabled')
        ->label('Disabled time')
        ->hourCycle(24)
        ->hideTimeZone()
        ->disabled()
        ->default('14:30'),
];
