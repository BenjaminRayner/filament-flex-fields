<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use BackedEnum;
use InvalidArgumentException;
use Stringable;

/** Text metadata is normalized explicitly, never through PHP's array/object casts. */
final class OptionText
{
    public static function required(mixed $value, string $attribute): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }
        if ($value instanceof Stringable) {
            $value = (string) $value;
        }
        if ($value !== null && ! is_scalar($value)) {
            throw new InvalidArgumentException("Select {$attribute} must be scalar text or Stringable.");
        }
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException("Select {$attribute} cannot be a non-finite number.");
        }

        return (string) $value;
    }

    public static function optional(mixed $value, string $attribute): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = self::required($value, $attribute);

        return trim($text) === '' ? null : $text;
    }
}
