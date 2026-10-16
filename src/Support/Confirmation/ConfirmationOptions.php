<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Confirmation;

use InvalidArgumentException;

final class ConfirmationOptions
{
    public static function milliseconds(mixed $value, string $name, int $minimum = 0, int $maximum = 60_000): int
    {
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException("{$name} must be an integer between {$minimum} and {$maximum} milliseconds.");
        }

        return $value;
    }

    /** @param list<string> $allowed */
    public static function choice(mixed $value, string $name, array $allowed): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported {$name}.");
        }

        return $value;
    }

    public static function text(mixed $value, string $name): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 240 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException("{$name} requires nonempty text of at most 240 characters.");
        }

        return $value;
    }
}
