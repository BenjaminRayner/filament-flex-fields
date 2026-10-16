<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CreditCard\Validation;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CreditCardField;
use Bjanczak\FilamentFlexFields\Support\CreditCard\CardValues;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CardRule implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private readonly CreditCardField $field) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! CardValues::acceptsShape($value)) {
            $fail(Translations::get('validation.array', ['attribute' => $attribute]));

            return;
        }
        if (! CardValues::acceptsFormatting($value)) {
            $fail(Translations::get('filament-flex-fields::default.validation.credit_card.invalid_number'));

            return;
        }
        $state = CardValues::preview($value);
        if ($this->field->isRequired()) {
            foreach (['number' => $this->field->getNumberLabel(), 'name' => $this->field->getNameLabel(),
                'expiry' => $this->field->getExpiryLabel(), 'cvv' => $this->field->getCvvLabel()] as $key => $label) {
                if ($state[$key] === '') {
                    $fail(Translations::get('validation.required', ['attribute' => $label]));

                    return;
                }
            }
        }
        if ($state['number'] !== '' && ! CardValues::passesLuhn($state['number'])) {
            $fail(Translations::get('filament-flex-fields::default.validation.credit_card.invalid_number'));

            return;
        }
        if (($error = CardValues::expiryError($state['expiry'])) !== null) {
            $fail($error);

            return;
        }
        if ($state['cvv'] !== '' && ! preg_match('/^[0-9]{3,4}$/D', $state['cvv'])) {
            $fail(Translations::get('filament-flex-fields::default.validation.credit_card.invalid_cvv'));

            return;
        }
        $this->field->passesPciTokenizationRules($state, $fail);
    }
}
