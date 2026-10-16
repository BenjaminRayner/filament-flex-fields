<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Currency;

use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;

final class CurrencyPresentation
{
    /** @return array{isEmpty: bool, negative: bool, segments: list<array{type: string, char: string, key: string, ghost: bool}>, symbol: string, currencyCode: string} */
    public static function initial(?int $amount, string $currency, string $locale): array
    {
        $precision = CurrencyCountries::decimals($currency);
        $edit = CurrencyCountries::editStateFromMinor($amount, $precision);
        $empty = $amount === null;

        return [
            'isEmpty' => $empty,
            'negative' => ! $empty && $edit['negative'],
            'segments' => $empty ? [] : array_values(array_filter(
                CurrencyCountries::buildDisplaySegments($edit, $locale, $precision),
                static fn (array $segment): bool => ! ($segment['type'] === 'digit' && $segment['ghost']),
            )),
            'symbol' => CurrencyCountries::symbol($currency), 'currencyCode' => $currency,
        ];
    }
}
