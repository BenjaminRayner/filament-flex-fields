<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Nps;

use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use InvalidArgumentException;

final class OptionValue
{
    public static function identity(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        if (is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value)) {
            return abs($value) <= NumericValue::MAX_SAFE_VALUE ? (string) $value : null;
        }

        return null;
    }

    /** @param array<array-key, mixed> $options */
    public static function assertOptions(array $options): void
    {
        foreach ($options as $key => $label) {
            if (self::identity($key) === null || (! is_string($label) && ! is_int($label))) {
                throw new InvalidArgumentException('NpsField options require nonempty browser-safe keys and text or integer labels.');
            }
        }
    }

    public static function tone(mixed $value): ?string
    {
        $key = self::identity($value);
        if ($key === null || ! preg_match('/^(?:[0-9]|10)$/D', $key)) {
            return null;
        }

        return match (true) {
            (int) $key <= 6 => 'detractor',
            (int) $key <= 8 => 'passive',
            default => 'promoter',
        };
    }
}
