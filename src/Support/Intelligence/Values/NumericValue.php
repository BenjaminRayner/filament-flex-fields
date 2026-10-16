<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Values;

use InvalidArgumentException;

final class NumericValue
{
    public const MAX_EXACT_INTEGER = 9007199254740991;

    public static function read(mixed $value, bool $nullAsZero = false): float
    {
        if (is_array($value) && array_key_exists('amount', $value)) {
            $value = $value['amount'];
        }
        if ($value === null || $value === '') {
            if ($nullAsZero) {
                return 0.0;
            }
            throw new InvalidArgumentException('Formula value must be numeric.');
        }
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Formula value must be numeric.');
        }

        return self::finite((float) $value);
    }

    public static function finite(float $value): float
    {
        if (! is_finite($value)) {
            throw new InvalidArgumentException('Formula values and intermediate results must be finite.');
        }

        return $value;
    }

    public static function normalize(float $value): float|int
    {
        self::finite($value);

        return floor($value) === $value && $value >= PHP_INT_MIN && $value < (float) PHP_INT_MAX
            ? (int) $value
            : $value;
    }
}
