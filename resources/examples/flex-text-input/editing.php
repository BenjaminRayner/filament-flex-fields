<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('flex_text_input__selectOnFocus_0')
    ->label('selectOnFocus(false)')
    ->selectOnFocus(false);

FlexTextInput::make('flex_text_input__selectOnFocus_1')
    ->label('selectOnFocus(true)')
    ->selectOnFocus(true);

FlexTextInput::make('flex_text_input__caretOnFocus_0')
    ->label('caretOnFocus(preserve)')
    ->caretOnFocus('preserve');

FlexTextInput::make('flex_text_input__caretOnFocus_1')
    ->label('caretOnFocus(start)')
    ->caretOnFocus('start');

FlexTextInput::make('flex_text_input__caretOnFocus_2')
    ->label('caretOnFocus(end)')
    ->caretOnFocus('end');

FlexTextInput::make('flex_text_input__enterKeyHint_0')
    ->label('enterKeyHint(null)')
    ->enterKeyHint(null);

FlexTextInput::make('flex_text_input__enterKeyHint_1')
    ->label('enterKeyHint(enter)')
    ->enterKeyHint('enter');

FlexTextInput::make('flex_text_input__enterKeyHint_2')
    ->label('enterKeyHint(done)')
    ->enterKeyHint('done');

FlexTextInput::make('flex_text_input__enterKeyHint_3')
    ->label('enterKeyHint(go)')
    ->enterKeyHint('go');

FlexTextInput::make('flex_text_input__enterKeyHint_4')
    ->label('enterKeyHint(next)')
    ->enterKeyHint('next');

FlexTextInput::make('flex_text_input__enterKeyHint_5')
    ->label('enterKeyHint(previous)')
    ->enterKeyHint('previous');

FlexTextInput::make('flex_text_input__enterKeyHint_6')
    ->label('enterKeyHint(search)')
    ->enterKeyHint('search');

FlexTextInput::make('flex_text_input__enterKeyHint_7')
    ->label('enterKeyHint(send)')
    ->enterKeyHint('send');

FlexTextInput::make('flex_text_input__normalizeOnBlur_0')
    ->label('normalizeOnBlur(none)')
    ->normalizeOnBlur('none');

FlexTextInput::make('flex_text_input__normalizeOnBlur_1')
    ->label('normalizeOnBlur(trim)')
    ->normalizeOnBlur('trim');

FlexTextInput::make('flex_text_input__normalizeOnBlur_2')
    ->label('normalizeOnBlur(lowercase)')
    ->normalizeOnBlur('lowercase');

FlexTextInput::make('flex_text_input__normalizeOnBlur_3')
    ->label('normalizeOnBlur(uppercase)')
    ->normalizeOnBlur('uppercase');

FlexTextInput::make('flex_text_input__normalizeOnBlur_4')
    ->label('normalizeOnBlur(collapse-whitespace)')
    ->normalizeOnBlur('collapse-whitespace');

FlexTextInput::make('flex_text_input__pasteMode_0')
    ->label('pasteMode(preserve)')
    ->pasteMode('preserve');

FlexTextInput::make('flex_text_input__pasteMode_1')
    ->label('pasteMode(plain-line)')
    ->pasteMode('plain-line');

FlexTextInput::make('flex_text_input__pasteMode_2')
    ->label('pasteMode(trim)')
    ->pasteMode('trim');

FlexTextInput::make('flex_text_input__unicodeNormalization_0')
    ->label('unicodeNormalization(none)')
    ->unicodeNormalization('none');

FlexTextInput::make('flex_text_input__unicodeNormalization_1')
    ->label('unicodeNormalization(NFC)')
    ->unicodeNormalization('NFC');

FlexTextInput::make('flex_text_input__unicodeNormalization_2')
    ->label('unicodeNormalization(NFKC)')
    ->unicodeNormalization('NFKC');

FlexTextInput::make('flex_text_input__readingTime_0')
    ->label('readingTime(false)')
    ->readingTime(false);

FlexTextInput::make('flex_text_input__readingTime_1')
    ->label('readingTime(true)')
    ->readingTime(true);

FlexTextInput::make('flex_text_input__readingWordsPerMinute_0')
    ->label('readingWordsPerMinute(200)')
    ->readingWordsPerMinute(200);

FlexTextInput::make('flex_text_input__autofocus')
    ->label('Native autofocus · explicit opt-in')
    ->autofocus(true);
