<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Currency;

use InvalidArgumentException;

/** Integer minor units remain exact across PHP and the JavaScript transport. */
final class CurrencyAmountNormalizer
{
    private const MAX_EXACT_INTEGER = 9007199254740991;

    public static function fromMajor(float|int|string|null $major, int $decimals): ?int
    {
        if ($major === null || $major === '' || is_float($major)) {
            return self::normalize($major, $decimals);
        }
        $major = trim((string) $major);

        return self::normalize(str_contains($major, '.') ? $major : $major.'.0', $decimals);
    }

    public static function normalize(mixed $amount, int $decimals): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        if ($decimals < 0 || $decimals > 9) {
            throw new InvalidArgumentException('Unsupported currency precision.');
        }

        if (is_int($amount)) {
            return self::integer((string) $amount);
        }
        if (is_float($amount)) {
            $scaled = round($amount * (10 ** $decimals));
            if (! is_finite($scaled) || abs($scaled) > self::MAX_EXACT_INTEGER) {
                throw new InvalidArgumentException('Currency amount exceeds the exact transport range.');
            }

            return (int) $scaled;
        }
        if (! is_string($amount)) {
            throw new InvalidArgumentException('Currency amount must be integer minor units or a decimal major-unit value.');
        }
        $amount = trim($amount);
        if (! str_contains($amount, '.')) {
            return self::integer($amount);
        }
        if (! preg_match('/^([+-]?)(\d*)\.(\d*)$/D', $amount, $matches) || ($matches[2] === '' && $matches[3] === '')) {
            throw new InvalidArgumentException('Currency major-unit value must be a plain decimal number.');
        }
        $fraction = $matches[3];
        $digits = ($matches[2] === '' ? '0' : $matches[2]).substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
        $minor = self::integer($digits);
        if (isset($fraction[$decimals]) && $fraction[$decimals] >= '5') {
            if ($minor === self::MAX_EXACT_INTEGER) {
                throw new InvalidArgumentException('Rounded currency amount exceeds the exact transport range.');
            }
            $minor++;
        }

        return $matches[1] === '-' ? -$minor : $minor;
    }

    private static function integer(string $amount): int
    {
        if (! preg_match('/^([+-]?)(\d+)$/D', $amount, $matches)) {
            throw new InvalidArgumentException('Currency minor units must be a plain integer.');
        }
        $digits = ltrim($matches[2], '0');
        if ($digits === '') {
            return 0;
        }
        $maximum = (string) self::MAX_EXACT_INTEGER;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw new InvalidArgumentException('Currency amount exceeds the exact transport range.');
        }

        return $matches[1] === '-' ? -(int) $digits : (int) $digits;
    }
}
