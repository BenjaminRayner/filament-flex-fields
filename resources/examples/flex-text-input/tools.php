<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;

FlexTextInput::make('flex_text_input__custom_tool_icons')
    ->label('Custom tool icons & labels')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->clearable(true)
    ->emojiIcon('hugeicons-look-top')
    ->microphoneIcon('flexhugeicons-mic-01')
    ->clearIcon('hugeicons-multiplication-sign')
    ->emojiPickerLabel('Insert emoji')
    ->speechDictationLabel('Speak')
    ->emojiPickerLocale('en')
    ->speechDictationLanguage('en-US');

FlexTextInput::make('flex_text_input__emojiCloseOnSelect_0')
    ->label('emojiCloseOnSelect(false)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->emojiCloseOnSelect(false);

FlexTextInput::make('flex_text_input__emojiCloseOnSelect_1')
    ->label('emojiCloseOnSelect(true)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->emojiCloseOnSelect(true);

FlexTextInput::make('flex_text_input__emojiPickerPlacement_0')
    ->label('emojiPickerPlacement(auto)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->emojiPickerPlacement('auto');

FlexTextInput::make('flex_text_input__emojiPickerPlacement_1')
    ->label('emojiPickerPlacement(top)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->emojiPickerPlacement('top');

FlexTextInput::make('flex_text_input__emojiPickerPlacement_2')
    ->label('emojiPickerPlacement(bottom)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->emojiPickerPlacement('bottom');

FlexTextInput::make('flex_text_input__emojiPickerWidth_0')
    ->label('emojiPickerWidth(304)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->emojiPickerWidth(304);

FlexTextInput::make('flex_text_input__speechDictationMode_0')
    ->label('speechDictationMode(append)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->speechDictationMode('append');

FlexTextInput::make('flex_text_input__speechDictationMode_1')
    ->label('speechDictationMode(replace-selection)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->speechDictationMode('replace-selection');

FlexTextInput::make('flex_text_input__speechDictationContinuous_0')
    ->label('speechDictationContinuous(false)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->speechDictationContinuous(false);

FlexTextInput::make('flex_text_input__speechDictationContinuous_1')
    ->label('speechDictationContinuous(true)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->speechDictationContinuous(true);

FlexTextInput::make('flex_text_input__speechDictationInterim_0')
    ->label('speechDictationInterim(false)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->speechDictationInterim(false);

FlexTextInput::make('flex_text_input__speechDictationInterim_1')
    ->label('speechDictationInterim(true)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->speechDictationInterim(true);

FlexTextInput::make('flex_text_input__speechDictationTimeout_0')
    ->label('speechDictationTimeout(60000)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->speechDictationTimeout(60000);

FlexTextInput::make('flex_text_input__speechDictationMessages_0')
    ->label('speechDictationMessages(…)')
    ->emojiPicker(true)
    ->speechDictation(true)
    ->speechDictationMessages([
        'not-allowed' => 'Microphone access denied.',
    ]);

// Associated icon: separate state, shared Icon Picker and native server validation.
FlexTextInput::make('title')
    ->iconPicker('title_icon', fn (IconPickerField $picker) => $picker
        ->label('Choose title icon')
        ->sets(['hugeicons', 'heroicons'])
        ->perPage(48)
        ->gridColumns(8)
        ->clearable());
