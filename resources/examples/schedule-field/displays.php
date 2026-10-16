<?php

use Bjanczak\FilamentFlexFields\Filament\Infolists\Components\ScheduleEntry;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ScheduleColumn;

// Table: only days with active working slots are displayed.
ScheduleColumn::make('opening_hours')
    ->label('Business hours')
    ->locale('en')
    ->hourCycle(24);

// Infolist: a complete weekly summary.
ScheduleEntry::make('opening_hours')
    ->heading('Business hours')
    ->description('Opening and closing times.')
    ->showClosedDays()
    ->showTimezone();

// Infolist: only active days, with a 12-hour clock.
ScheduleEntry::make('opening_hours')
    ->showClosedDays(false)
    ->showTimezone(false)
    ->hourCycle(12)
    ->days(['mon', 'wed', 'fri']);

// Localized weekday badges, starting on Sunday.
ScheduleColumn::make('opening_hours')
    ->locale('pl')
    ->weekStartsOn(0);

// Arabic infolist, right-to-left layout and a 12-hour clock.
ScheduleEntry::make('opening_hours')
    ->locale('ar')
    ->weekStartsOn(0)
    ->rtl()
    ->hourCycle(12);
