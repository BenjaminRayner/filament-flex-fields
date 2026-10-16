<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CreditCard\Storage;

use Bjanczak\FilamentFlexFields\Support\CreditCard\CardValues;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Validation\ValidationException;
use Throwable;

/** One field-local tokenization result; no static cache or retained raw PAN. */
final class CardStorage
{
    private ?string $fingerprint = null;

    private ?Closure $tokenizer = null;

    private ?string $token = null;

    private function tokenize(string $number): ?string
    {
        $callback = MediaCaptureOs::tokenizeCreditCardCallback();
        $fingerprint = hash('sha256', $number);
        if ($this->fingerprint === $fingerprint && $this->tokenizer === $callback) {
            return $this->token;
        }
        $this->fingerprint = $fingerprint;
        $this->tokenizer = $callback;
        $this->token = null;
        try {
            $this->token = MediaCaptureOs::tokenizePrimaryAccountNumber($number);
        } catch (Throwable) { /* Provider failure must never fall back to storing PAN. */
        }

        return $this->token;
    }

    public function validateTokenization(string $number, Closure $fail): bool
    {
        if ($number === '') {
            return true;
        }
        $callback = MediaCaptureOs::tokenizeCreditCardCallback();
        if ($callback === null) {
            if (! MediaCaptureOs::shouldNeverStorePan() && ! MediaCaptureOs::shouldRequireCreditCardTokenization()) {
                return true;
            }
            $fail(Translations::get('filament-flex-fields::default.validation.credit_card.tokenization_required'));

            return false;
        }
        if ($this->tokenize($number) !== null) {
            return true;
        }
        $fail(Translations::get('filament-flex-fields::default.validation.credit_card.tokenization_failed'));

        return false;
    }

    /** @return array<string,string> */
    public function dehydrate(mixed $value): array
    {
        if (! CardValues::acceptsFormatting($value)) {
            throw ValidationException::withMessages(['card' => Translations::get('filament-flex-fields::default.validation.credit_card.invalid_number')]);
        }
        $state = CardValues::preview($value);
        if (($state['number'] !== '' && ! CardValues::passesLuhn($state['number']))
            || CardValues::expiryError($state['expiry']) !== null
            || ($state['cvv'] !== '' && ! preg_match('/^[0-9]{3,4}$/D', $state['cvv']))) {
            throw ValidationException::withMessages(['card' => Translations::get('filament-flex-fields::default.validation.credit_card.invalid_number')]);
        }
        $number = $state['number'];
        $metadata = ['name' => $state['name'], 'expiry' => $state['expiry']];
        if ($number !== '') {
            $token = $this->tokenize($number);
            if ($token !== null) {
                return ['token' => $token, 'last4' => substr($number, -4), ...$metadata];
            }
            if (MediaCaptureOs::tokenizeCreditCardCallback() !== null) {
                throw ValidationException::withMessages(['card' => Translations::get('filament-flex-fields::default.validation.credit_card.tokenization_failed')]);
            }
            if (MediaCaptureOs::shouldNeverStorePan() || MediaCaptureOs::shouldRequireCreditCardTokenization()) {
                return ['last4' => substr($number, -4), ...$metadata];
            }
        }

        return ['number' => $number, ...$metadata];
    }
}
