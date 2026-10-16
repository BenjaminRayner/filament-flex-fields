<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CreditCardField;

// Register your MediaCaptureOs tokenizer before submitting card details.
CreditCardField::make('payment')
    ->label('Payment card')
    ->variant('midnight')
    ->required();

CreditCardField::make('branded_card')
    ->mark('Acme')
    ->variant('ocean')
    ->inputVariant('secondary')
    ->size('lg')
    ->rounding('lg');

CreditCardField::make('compact_card')
    ->size('sm')
    ->variant('slate')
    ->flipOnCvvFocus(false)
    ->numberLabel('Card number')
    ->nameLabel('Cardholder')
    ->expiryLabel('Expires')
    ->cvvLabel('Security code');

CreditCardField::make('review_card')
    ->variant('sunset')
    ->readOnly();
