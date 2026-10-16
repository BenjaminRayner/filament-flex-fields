<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rating;

use InvalidArgumentException;

final class RatingValue
{
    public const MAX_ITEMS = 100;

    public static function assertMaximum(int $max): int
    {
        if ($max < 1 || $max > self::MAX_ITEMS) {
            throw new InvalidArgumentException('Rating max must be between 1 and 100.');
        }

        return $max;
    }

    public static function numeric(mixed $value): ?float
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    public static function display(mixed $value, int $max): ?float
    {
        $number = self::numeric($value);

        return $number === null || $number < 0 ? null : min($number, (float) $max);
    }

    public static function fill(float|int|null $value, int $index): float
    {
        return $value === null || ! is_finite((float) $value) ? 0.0 : max(0.0, min(1.0, $value - ($index - 1)));
    }
}
