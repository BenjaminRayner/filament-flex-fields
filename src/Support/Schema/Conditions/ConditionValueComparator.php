<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Conditions;

final class ConditionValueComparator
{
    public static function matches(string $operator, mixed $actual, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => self::equal($actual, $expected),
            'not_equals' => ! self::equal($actual, $expected),
            'filled' => filled($actual),
            'empty' => blank($actual),
            'in' => is_array($expected) && self::oneOf($actual, $expected),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'not_contains' => is_string($actual) && is_string($expected) && ! str_contains($actual, $expected),
            'greater_than' => self::numeric($actual) && self::numeric($expected) && $actual > $expected,
            'less_than' => self::numeric($actual) && self::numeric($expected) && $actual < $expected,
            default => false,
        };
    }

    private static function equal(mixed $actual, mixed $expected): bool
    {
        if (self::numeric($actual) && self::numeric($expected)) {
            // PHP compares int/string numerically without first truncating all ints to float.
            return $actual == $expected;
        }

        return $actual === $expected;
    }

    private static function numeric(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value);
    }

    /** @param array<array-key, mixed> $values */
    private static function oneOf(mixed $actual, array $values): bool
    {
        foreach ($values as $value) {
            if (self::equal($actual, $value)) {
                return true;
            }
        }

        return false;
    }
}
