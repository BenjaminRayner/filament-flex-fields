<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\NumberStepper;

use InvalidArgumentException;

final class NumericValue
{
    public const MAX_SAFE_VALUE = 9007199254740991;

    public static function parse(mixed $value): int|float|null
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! is_numeric($value)) {
            return null;
        }

        $number = $value + 0;

        return is_finite((float) $number) && abs($number) <= self::MAX_SAFE_VALUE ? $number : null;
    }

    public static function bound(mixed $value, string $name): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::parse($value) ?? throw new InvalidArgumentException("NumberStepper {$name} requires a finite, browser-safe number.");
    }

    public static function round(float $value): float
    {
        $lower = floor($value);

        return $value - $lower < 0.5 ? $lower : $lower + 1;
    }
}
