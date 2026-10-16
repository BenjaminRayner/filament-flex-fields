<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\SwitchField;

/** Accepted boolean representations only; malformed values remain available to validation. */
final class BooleanValue
{
    public static function normalize(mixed $value): mixed
    {
        return match ($value) {
            true, 1, '1' => true,
            false, 0, '0', null => false,
            default => $value,
        };
    }

    public static function checked(mixed $value): bool
    {
        return self::normalize($value) === true;
    }
}
