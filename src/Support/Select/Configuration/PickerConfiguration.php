<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Configuration;

use InvalidArgumentException;

/** Explicit configuration boundary for picker callbacks; no array-to-string/number casts. */
final class PickerConfiguration
{
    public static function text(mixed $value, string $attribute): string
    {
        if (! is_string($value) || strlen($value) > 65536 || ! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException("Picker {$attribute} must resolve to bounded UTF-8 text.");
        }

        return $value;
    }

    public static function optionalText(mixed $value, string $attribute): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = self::text($value, $attribute);

        return trim($text) === '' ? null : $text;
    }

    /** @param list<string> $allowed */
    public static function choice(mixed $value, array $allowed, string $attribute): string
    {
        $text = self::text($value, $attribute);
        if (! in_array($text, $allowed, true)) {
            throw new InvalidArgumentException("Picker {$attribute} [{$text}] is not supported.");
        }

        return $text;
    }

    public static function number(mixed $value, string $attribute): float
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! is_numeric($value)) {
            throw new InvalidArgumentException("Picker {$attribute} must resolve to a finite number.");
        }
        $number = (float) $value;
        if (! is_finite($number)) {
            throw new InvalidArgumentException("Picker {$attribute} must resolve to a finite number.");
        }

        return $number;
    }

    public static function integer(mixed $value, string $attribute): int
    {
        if (is_int($value)) {
            return $value;
        }
        // Preserve exact integer strings instead of passing them through floating point.
        if (is_string($value) && preg_match('/^-?\d+$/D', $value)) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if ($integer !== false) {
                return $integer;
            }
        }
        $number = self::number($value, $attribute);
        if (floor($number) !== $number || $number >= (float) PHP_INT_MAX || $number < (float) PHP_INT_MIN) {
            throw new InvalidArgumentException("Picker {$attribute} must resolve to a representable integer.");
        }

        return (int) $number;
    }
}
