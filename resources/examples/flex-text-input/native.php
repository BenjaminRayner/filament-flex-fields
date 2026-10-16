<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('flex_text_input__numeric')
    ->label('Native integer · 0–100')
    ->integer(true)
    ->minValue(0)
    ->maxValue(100)
    ->required(true)
    ->enterKeyHint('done');

FlexTextInput::make('flex_text_input__decimal')
    ->label('Numeric · step .01')
    ->numeric(true)
    ->step(0.01)
    ->minValue(0)
    ->maxValue(100);

FlexTextInput::make('flex_text_input__telephone')
    ->label('Native telephone')
    ->tel(true)
    ->autocomplete('tel');

FlexTextInput::make('flex_text_input__url')
    ->label('Native URL')
    ->url(true)
    ->autocomplete('url');

FlexTextInput::make('flex_text_input__type')
    ->label('Explicit native type')
    ->type('search')
    ->placeholder('Search…');

FlexTextInput::make('flex_text_input__masked')
    ->label('Native mask · postal code')
    ->mask('99-999')
    ->stripCharacters('-');

FlexTextInput::make('flex_text_input__datalist')
    ->label('Native datalist')
    ->datalist([
        0 => 'Laravel',
        1 => 'Filament',
        2 => 'Livewire',
    ]);

FlexTextInput::make('flex_text_input__disabled')
    ->label('Disabled · tools revoked')
    ->disabled(true)
    ->emojiPicker(true)
    ->speechDictation(true)
    ->clearable(true);

FlexTextInput::make('flex_text_input__readonly')
    ->label('Read only · copy remains available')
    ->readOnly(true)
    ->copyable(true);

FlexTextInput::make('flex_text_input__native_affixes')
    ->label('Native non-inline affixes')
    ->prefix('https://', false)
    ->suffix('.com', false)
    ->inlinePrefix(false)
    ->inlineSuffix(false);

FlexTextInput::make('flex_text_input__native_icons')
    ->label('Native icons & colors')
    ->prefixIcon('hugeicons-mail-01', false)
    ->suffixIcon('hugeicons-tick-02', false)
    ->prefixIconColor('primary')
    ->suffixIconColor('success');

FlexTextInput::make('flex_text_input__attributes')
    ->label('Native attributes · lazy state')
    ->extraInputAttributes([
        'data-demo-native' => 'true',
        'aria-describedby' => 'native-demo-help',
    ])
    ->extraAlpineAttributes([
        'data-demo-alpine' => 'true',
    ])
    ->lazy(true)
    ->autocapitalize('sentences')
    ->autocomplete('off')
    ->inputMode('text')
    ->minLength(2)
    ->maxLength(80)
    ->spellcheck(true)
    ->helperText('Native state, validation and accessibility attributes.');
