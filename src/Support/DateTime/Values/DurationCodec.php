<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DateTime\Values;

/** Elapsed time has no timezone and may exceed 24 hours. */
final class DurationCodec
{
    public const MAX_SECONDS = 359999999;

    public static function seconds(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^(\d{1,5}):([0-5][0-9])(?::([0-5][0-9]))?$/D', trim($value), $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * 3600 + (int) $parts[2] * 60 + (int) ($parts[3] ?? 0);
    }

    public static function format(int $seconds, bool $withSeconds): string
    {
        $value = sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));

        return $withSeconds ? $value.sprintf(':%02d', $seconds % 60) : $value;
    }
}
