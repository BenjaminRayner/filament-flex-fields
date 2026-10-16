<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Phone;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Closure;
use InvalidArgumentException;

/** Array-shaped fields need required validation of the national number, not the wrapper array. */
final class PhoneValidation
{
    public static function forField(PhoneField $field): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            if (! is_array($value)) {
                $fail(__('validation.array', ['attribute' => $attribute]));

                return;
            }
            $country = $value['country'] ?? null;
            $national = $value['national'] ?? null;
            if (! is_string($country) || ! in_array($country, $field->getResolvedCountryCodes(), true)
                || ! is_string($national) || strlen($national) > 256
                || preg_match('~^[+0-9()\s.\-/]*$~uD', $national) !== 1) {
                $fail(__('filament-flex-fields::default.validation.phone.invalid'));

                return;
            }
            try {
                $state = $field->normalizeState($value);
                $message = $field->isRequired() && $state['national'] === ''
                    ? __('validation.required', ['attribute' => $field->getLabel()])
                    : $field->getPhoneValidationMessage($state);
            } catch (InvalidArgumentException) {
                $message = __('filament-flex-fields::default.validation.phone.invalid');
            }
            if ($message !== null) {
                $fail($message);
            }
        };
    }
}
