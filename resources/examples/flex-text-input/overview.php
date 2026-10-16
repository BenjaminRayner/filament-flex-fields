<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('flex_text_input__basic')
    ->label('Text · emoji · dictation')
    ->placeholder('Type something…')
    ->maxLength(80)
    ->characterCounter(true)
    ->emojiPicker(true)
    ->speechDictation(true);

FlexTextInput::make('flex_text_input__email')
    ->label('Email · verification')
    ->email(true)
    ->autofocus(false)
    ->caretOnFocus('end')
    ->autocomplete('off')
    ->prefixIcon('hugeicons-mail-01')
    ->verificationStatus('Verified')
    ->verificationStatusIcon('hugeicons-checkmark-badge-01')
    ->verificationStatusColor('success');

FlexTextInput::make('flex_text_input__password')
    ->label('Password · native reveal')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true);

FlexTextInput::make('flex_text_input__domain')
    ->label('Inline affixes')
    ->prefix('https://')
    ->suffix('.com');

FlexTextInput::make('flex_text_input__slug')
    ->label('Clear · native live validation')
    ->placeholder('my-product-name')
    ->maxLength(60)
    ->characterCounter(true)
    ->clearable(true)
    ->live(false, 500)
    ->validating(true);

FlexTextInput::make('flex_text_input__unicode')
    ->label('Graphemes · family emoji = 1')
    ->counterMode('graphemes')
    ->characterCounter(true)
    ->counterDisplay('both')
    ->softLimit(24)
    ->emojiPicker(true)
    ->clearable(true);
