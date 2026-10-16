<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('flex_text_input__custom_copy')
    ->label('Custom copy feedback')
    ->copyable(true, 'Copied!', 1200)
    ->copyIcon('hugeicons-copy-01')
    ->copyFeedback('both');

FlexTextInput::make('flex_text_input__loading')
    ->label('Inline loading · no empty toolbar')
    ->loading(true)
    ->live(false, 500)
    ->clearable(true);

FlexTextInput::make('flex_text_input__clearValue_0')
    ->label('clearValue()')
    ->clearable(true)
    ->copyable(true)
    ->clearValue('');

FlexTextInput::make('flex_text_input__clearLabel_0')
    ->label('clearLabel(Clear)')
    ->clearable(true)
    ->copyable(true)
    ->clearLabel('Clear');

FlexTextInput::make('flex_text_input__clearConfirmation_0')
    ->label('clearConfirmation(false)')
    ->clearable(true)
    ->copyable(true)
    ->clearConfirmation(false);

FlexTextInput::make('flex_text_input__clearConfirmation_1')
    ->label('clearConfirmation(true)')
    ->clearable(true)
    ->copyable(true)
    ->clearConfirmation(true);

FlexTextInput::make('flex_text_input__clearConfirmationLabel_0')
    ->label('clearConfirmationLabel(Clear this value?)')
    ->clearable(true)
    ->copyable(true)
    ->clearConfirmationLabel('Clear this value?');

FlexTextInput::make('flex_text_input__copyFeedback_0')
    ->label('copyFeedback(icon)')
    ->clearable(true)
    ->copyable(true)
    ->copyFeedback('icon');

FlexTextInput::make('flex_text_input__copyFeedback_1')
    ->label('copyFeedback(message)')
    ->clearable(true)
    ->copyable(true)
    ->copyFeedback('message');

FlexTextInput::make('flex_text_input__copyFeedback_2')
    ->label('copyFeedback(both)')
    ->clearable(true)
    ->copyable(true)
    ->copyFeedback('both');

FlexTextInput::make('flex_text_input__copyMode_0')
    ->label('copyMode(raw)')
    ->clearable(true)
    ->copyable(true)
    ->copyMode('raw');

FlexTextInput::make('flex_text_input__copyMode_1')
    ->label('copyMode(selection)')
    ->clearable(true)
    ->copyable(true)
    ->copyMode('selection');

FlexTextInput::make('flex_text_input__copyMode_2')
    ->label('copyMode(trimmed)')
    ->clearable(true)
    ->copyable(true)
    ->copyMode('trimmed');

FlexTextInput::make('flex_text_input__copyTransform_0')
    ->label('copyTransform(none)')
    ->clearable(true)
    ->copyable(true)
    ->copyTransform('none');

FlexTextInput::make('flex_text_input__copyTransform_1')
    ->label('copyTransform(uppercase)')
    ->clearable(true)
    ->copyable(true)
    ->copyTransform('uppercase');

FlexTextInput::make('flex_text_input__copyTransform_2')
    ->label('copyTransform(lowercase)')
    ->clearable(true)
    ->copyable(true)
    ->copyTransform('lowercase');

FlexTextInput::make('flex_text_input__copyErrorLabel_0')
    ->label('copyErrorLabel(Could not copy. Please copy the text manually.)')
    ->clearable(true)
    ->copyable(true)
    ->copyErrorLabel('Could not copy. Please copy the text manually.');

FlexTextInput::make('flex_text_input__toolsOrder_0')
    ->label('toolsOrder(…)')
    ->clearable(true)
    ->copyable(true)
    ->toolsOrder([
        0 => 'actions',
        1 => 'clear',
        2 => 'emoji',
        3 => 'dictation',
    ])
    ->emojiPicker(true)
    ->speechDictation(true);

FlexTextInput::make('flex_text_input__toolsVisibility_0')
    ->label('toolsVisibility(always)')
    ->clearable(true)
    ->copyable(true)
    ->toolsVisibility('always')
    ->emojiPicker(true)
    ->speechDictation(true);

FlexTextInput::make('flex_text_input__toolsVisibility_1')
    ->label('toolsVisibility(focus)')
    ->clearable(true)
    ->copyable(true)
    ->toolsVisibility('focus')
    ->emojiPicker(true)
    ->speechDictation(true);

FlexTextInput::make('flex_text_input__toolsVisibility_2')
    ->label('toolsVisibility(hover)')
    ->clearable(true)
    ->copyable(true)
    ->toolsVisibility('hover')
    ->emojiPicker(true)
    ->speechDictation(true);
