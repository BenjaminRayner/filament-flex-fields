<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('flex_text_input__counterMode_0')
    ->label('counterMode(characters)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterMode('characters');

FlexTextInput::make('flex_text_input__counterMode_1')
    ->label('counterMode(graphemes)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterMode('graphemes');

FlexTextInput::make('flex_text_input__counterMode_2')
    ->label('counterMode(words)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterMode('words');

FlexTextInput::make('flex_text_input__counterMode_3')
    ->label('counterMode(bytes)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterMode('bytes');

FlexTextInput::make('flex_text_input__counterDisplay_0')
    ->label('counterDisplay(used)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterDisplay('used');

FlexTextInput::make('flex_text_input__counterDisplay_1')
    ->label('counterDisplay(remaining)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterDisplay('remaining');

FlexTextInput::make('flex_text_input__counterDisplay_2')
    ->label('counterDisplay(both)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterDisplay('both');

FlexTextInput::make('flex_text_input__counterWarningAt_0')
    ->label('counterWarningAt(0.85)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterWarningAt(0.85);

FlexTextInput::make('flex_text_input__softLimit_0')
    ->label('softLimit(12)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->softLimit(12);

FlexTextInput::make('flex_text_input__counterLimit_0')
    ->label('counterLimit(12)')
    ->characterCounter(true)
    ->counterLimit(12);

FlexTextInput::make('flex_text_input__enforceCounterLimit_0')
    ->label('enforceCounterLimit(false)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->enforceCounterLimit(false);

FlexTextInput::make('flex_text_input__enforceCounterLimit_1')
    ->label('enforceCounterLimit(true)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->enforceCounterLimit(true);

FlexTextInput::make('flex_text_input__counterTemplate_0')
    ->label('counterTemplate({count} / {limit} · {remaining})')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterTemplate('{count} / {limit} · {remaining}');

FlexTextInput::make('flex_text_input__counterLocale_0')
    ->label('counterLocale(pl-PL)')
    ->characterCounter(true)
    ->counterLimit(24)
    ->counterLocale('pl-PL');
