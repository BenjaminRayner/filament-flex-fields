<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\PriceRange;

use InvalidArgumentException;

final class RangeValues
{
    public static function number(mixed $value): ?float
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return is_finite($number) && abs($number) <= 9007199254740991 ? $number : null;
    }

    /** @return array{min: int|float, max: int|float}|null */
    public static function pair(mixed $value): ?array
    {
        if (! is_array($value) || count($value) !== 2 || ! array_key_exists('min', $value) || ! array_key_exists('max', $value)) {
            return null;
        }
        $min = self::number($value['min']);
        $max = self::number($value['max']);
        if ($min === null || $max === null) {
            return null;
        }

        return ['min' => self::canonical($min), 'max' => self::canonical($max)];
    }

    public static function canonical(float $number): int|float
    {
        return floor($number) === $number ? (int) $number : $number;
    }

    /** @param list<int|float> $heights
     * @return list<float>
     */
    public static function histogram(array $heights): array
    {
        if (! array_is_list($heights) || count($heights) > 1024) {
            throw new InvalidArgumentException('Price range histogram must be a list with at most 1024 heights.');
        }

        return array_map(static function (mixed $height): float {
            $value = self::number($height);
            if ($value === null) {
                throw new InvalidArgumentException('Price range histogram heights must be finite numbers.');
            }

            return max(8.0, min(100.0, $value));
        }, $heights);
    }
}
