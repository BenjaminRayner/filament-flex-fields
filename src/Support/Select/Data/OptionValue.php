<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use BackedEnum;

/** Canonical transport identity without casting arbitrary submitted structures. */
final class OptionValue
{
    public static function normalize(mixed $value): string|int|null
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }
        if (is_bool($value)) {
            $value = (int) $value;
        }

        return is_string($value) || is_int($value) ? $value : null;
    }
}
