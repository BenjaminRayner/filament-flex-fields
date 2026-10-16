<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('flex_text_input__textDirection_0')
    ->label('textDirection(null)')
    ->textDirection(null);

FlexTextInput::make('flex_text_input__textDirection_1')
    ->label('textDirection(auto)')
    ->textDirection('auto');

FlexTextInput::make('flex_text_input__textDirection_2')
    ->label('textDirection(ltr)')
    ->textDirection('ltr');

FlexTextInput::make('flex_text_input__textDirection_3')
    ->label('textDirection(rtl)')
    ->textDirection('rtl');

FlexTextInput::make('flex_text_input__primary')
    ->label('variant(primary)')
    ->variant('primary')
    ->emojiPicker(true);

FlexTextInput::make('flex_text_input__secondary')
    ->label('variant(secondary)')
    ->variant('secondary')
    ->emojiPicker(true);

FlexTextInput::make('flex_text_input__soft')
    ->label('variant(soft)')
    ->variant('soft')
    ->emojiPicker(true);

FlexTextInput::make('flex_text_input__flat')
    ->label('variant(flat)')
    ->variant('flat')
    ->emojiPicker(true);

FlexTextInput::make('flex_text_input__sm')
    ->label('size(sm)')
    ->size('sm')
    ->speechDictation(true);

FlexTextInput::make('flex_text_input__md')
    ->label('size(md)')
    ->size('md')
    ->speechDictation(true);

FlexTextInput::make('flex_text_input__lg')
    ->label('size(lg)')
    ->size('lg')
    ->speechDictation(true);

FlexTextInput::make('flex_text_input__rounding_native')
    ->label('rounding(native)')
    ->rounding('native')
    ->clearable(true);

FlexTextInput::make('flex_text_input__rounding_none')
    ->label('rounding(none)')
    ->rounding('none')
    ->clearable(true);

FlexTextInput::make('flex_text_input__rounding_sm')
    ->label('rounding(sm)')
    ->rounding('sm')
    ->clearable(true);

FlexTextInput::make('flex_text_input__rounding_md')
    ->label('rounding(md)')
    ->rounding('md')
    ->clearable(true);

FlexTextInput::make('flex_text_input__rounding_lg')
    ->label('rounding(lg)')
    ->rounding('lg')
    ->clearable(true);

FlexTextInput::make('flex_text_input__rounding_xl')
    ->label('rounding(xl)')
    ->rounding('xl')
    ->clearable(true);

FlexTextInput::make('flex_text_input__rounding_full')
    ->label('rounding(full)')
    ->rounding('full')
    ->clearable(true);

FlexTextInput::make('flex_text_input__rtl')
    ->label('RTL · Arabic')
    ->textDirection('rtl')
    ->characterCounter(true)
    ->emojiPicker(true)
    ->clearable(true);

FlexTextInput::make('flex_text_input__focus_off')
    ->label('focusOutline(false)')
    ->focusOutline(false);
