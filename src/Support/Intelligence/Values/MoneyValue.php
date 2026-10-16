<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Values;

use InvalidArgumentException;

final class MoneyValue
{
    public static function major(mixed $value, int $fractionDigits = 2, bool $nullAsZero = false): float
    {
        $number = NumericValue::read($value, $nullAsZero);
        if (abs($number) > NumericValue::MAX_EXACT_INTEGER) {
            throw new InvalidArgumentException('Money amount exceeds the exact calculation range.');
        }

        return $number / self::scale($fractionDigits);
    }

    public static function minor(float|int $value, int $fractionDigits = 2): int
    {
        $rounded = round(NumericValue::finite((float) $value) * self::scale($fractionDigits));
        if (! is_finite($rounded) || abs($rounded) > NumericValue::MAX_EXACT_INTEGER) {
            throw new InvalidArgumentException('Money amount exceeds the exact calculation range.');
        }

        return (int) $rounded;
    }

    private static function scale(int $fractionDigits): int
    {
        if ($fractionDigits < 0 || $fractionDigits > 6) {
            throw new InvalidArgumentException('Money fraction digits must be between 0 and 6.');
        }

        return 10 ** $fractionDigits;
    }
}
