<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;

// Native text validation, immediate counter and bounded animated autosize.
FlexTextareaField::make('message')
    ->label('Message')
    ->required()
    ->minLength(3)
    ->maxLength(500)
    ->characterCounter()
    ->maxHeight('12rem');

// A fixed-height alternative with opt-in whitespace trimming.
FlexTextareaField::make('notes')
    ->variant('secondary')
    ->autosize(false)
    ->rows(5)
    ->trim();

// The shared emoji picker and browser dictation, loaded when needed.
FlexTextareaField::make('prompt')
    ->emojiPicker()
    ->emojiPickerLocale('pl')
    ->speechDictation()
    ->speechDictationLanguage('pl-PL')
    ->speechDictationLabel('Dictate')
    ->variant('soft')
    ->rounding('xl');

// Declare the sibling state so toolbar selections persist and validate.
[
    Hidden::make('model')->default('fast')->required()->in(['fast', 'balanced']),
    FlexTextareaField::make('response')
        ->toolbarSelect('model', [
            'fast' => 'Fast',
            'balanced' => 'Balanced',
        ], icon: 'hugeicons-ai-brain-01', placeholder: 'Model'),
];

// A native Filament action with server validation and an instance loading target.
FlexTextareaField::make('chat_message')
    ->maxLength(500)
    ->submitAction(
        Action::make('send')
            ->label('Send message')
            ->icon('hugeicons-sent')
            ->action(function (FlexTextareaField $component): void {
                validator(['message' => $component->getState()], [
                    'message' => ['required', 'string', 'max:500'],
                ])->validate();

                Notification::make()->title('Message accepted')->success()->send();
            }),
    );

// Size and radius inherit the same configuration as other FLEX inputs.
FlexTextareaField::make('description')
    ->size('lg')
    ->rounding('xl')
    ->animatedAutosize(false)
    ->maxHeight(null)
    ->footer('Automatic height without animation.');
