<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Filament\Actions\Action;
use Filament\Support\RawJs;

// Same examples as the Assistance and Native compatibility demo groups.
// Limit your database query to suggestionLimit() records. Free text remains allowed.
FlexTextInput::make('flex_text_input__async_suggestions')
    ->label('Async suggestions · bounded request')
    ->suggestionsUsing(fn (string $search): array => array_values(array_filter(
        ['Amsterdam', 'Athens', 'Berlin', 'Lisbon', 'London', 'Paris', 'Warsaw'],
        fn (string $city): bool => str_contains(strtolower($city), strtolower($search)),
    )))
    ->suggestionMinLength(1);

// Advisory feedback; native email validation still controls form submission.
FlexTextInput::make('contact_email')
    ->email()
    ->verifyUsing(fn (string $value): array => [
        'state' => filter_var($value, FILTER_VALIDATE_EMAIL) ? 'success' : 'error',
        'message' => filter_var($value, FILTER_VALIDATE_EMAIL) ? 'Valid email format' : 'Enter a valid email address',
    ])
    ->verificationDebounce(300)
    ->verificationMinLength(3);

FlexTextInput::make('flex_text_input__money_mask')
    ->mask(RawJs::make('$money($input)'))
    ->numeric()
    ->stripCharacters(',');

FlexTextInput::make('flex_text_input__custom_actions')
    ->prefixAction(Action::make('prefixHelp')->label('Prefix action activated')->icon(FlexIcon::Envelope)
        ->livewireClickHandlerEnabled(false)->alpineClickHandler("showStatus('Prefix action activated')"))
    ->suffixAction(Action::make('suffixHelp')->label('Suffix action activated')->icon(FlexIcon::Copy)
        ->livewireClickHandlerEnabled(false)->alpineClickHandler("showStatus('Suffix action activated')"))
    ->clearable()
    ->helperText('Both native actions show local feedback; they do not submit or refresh this demo.');

// Conditional capabilities stop their active work when disabled after a Livewire update.
FlexTextInput::make('flex_text_input__dynamic_tools')
    ->emojiPicker(fn (mixed $state): bool => $state !== 'off')
    ->speechDictation(fn (mixed $state): bool => $state !== 'off')
    ->live(debounce: 300);
