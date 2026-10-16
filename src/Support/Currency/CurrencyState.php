<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Currency;

use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;

/** Hydration preserves the documented minor-unit storage contract. */
final class CurrencyState
{
    /** @return int|array{amount: ?int, currency: string}|null */
    public static function normalize(mixed $value, CurrencyCatalog $catalog): int|array|null
    {
        $currency = $catalog->selectable && is_array($value)
            ? $catalog->currency($value['currency'] ?? null) : $catalog->default;
        $amount = CurrencyAmountNormalizer::normalize(
            is_array($value) ? ($value['amount'] ?? null) : $value,
            CurrencyCountries::decimals($currency),
        );

        return $catalog->selectable ? ['amount' => $amount, 'currency' => $currency] : $amount;
    }

    /** @param int|array{amount: ?int, currency: string}|null $value */
    public static function amount(int|array|null $value): ?int
    {
        return is_array($value) ? ($value['amount'] ?? null) : $value;
    }
}
