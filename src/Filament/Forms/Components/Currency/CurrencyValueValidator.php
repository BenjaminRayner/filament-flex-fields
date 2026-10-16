<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Currency;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Closure;
use InvalidArgumentException;

/** Server authority for minor units, permitted codes and currency-specific limits. */
final class CurrencyValueValidator
{
    public static function validate(CurrencyField $field, mixed $value, Closure $fail): void
    {
        if ($field->hasCurrencySelect() && is_array($value) && array_key_exists('currency', $value)) {
            $code = $value['currency'];
            if (! is_string($code) || ! in_array(strtoupper(trim($code)), $field->getResolvedCurrencyCodes(), true)) {
                $fail(__('filament-flex-fields::default.validation.currency.invalid_code'));

                return;
            }
        }
        try {
            $state = $field->normalizeState($value);
        } catch (InvalidArgumentException) {
            $fail(__('validation.numeric', ['attribute' => $field->getLabel()]));

            return;
        }
        $amount = $field->extractAmount($state);
        $currency = $field->extractCurrency($state);
        if ($amount === null) {
            if ($field->isRequired()) {
                $fail(__('validation.required', ['attribute' => $field->getLabel()]));
            }

            return;
        }
        if (! $field->allowsNegative() && $amount < 0) {
            $fail(__('filament-flex-fields::default.validation.currency.negative'));

            return;
        }
        foreach (['min', 'max'] as $bound) {
            $limit = $bound === 'min' ? $field->getMinMinorUnits($currency) : $field->getMaxMinorUnits($currency);
            if ($limit === null || ($bound === 'min' ? $amount >= $limit : $amount <= $limit)) {
                continue;
            }
            $major = $bound === 'min' ? $field->getMin() : $field->getMax();
            $fail(__('filament-flex-fields::default.validation.currency.'.$bound, [
                $bound => CurrencyCountries::formatMajor($major, $currency, $field->getLocale($currency)),
            ]));

            return;
        }
    }
}
