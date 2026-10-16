<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

/** Bounded accounting without serialization, user callbacks or object retention. */
final class RenderCacheWeight
{
    public static function estimate(mixed $value, int $limit): ?int
    {
        $nodes = 0;

        return self::visit($value, $limit, $nodes, 0);
    }

    private static function visit(mixed $value, int $limit, int &$nodes, int $depth): ?int
    {
        if (++$nodes > 1024 || $depth > 16 || $limit < 1) {
            return null;
        }
        if (is_string($value)) {
            return strlen($value) <= $limit ? strlen($value) : null;
        }
        if (is_scalar($value) || $value === null) {
            return $limit >= 16 ? 16 : null;
        }
        if (! is_array($value)) {
            return null;
        }
        $bytes = 64;
        foreach ($value as $key => $entry) {
            $bytes += 32 + (is_string($key) ? strlen($key) : 8);
            $weight = self::visit($entry, $limit - $bytes, $nodes, $depth + 1);
            if ($weight === null) {
                return null;
            }
            $bytes += $weight;
        }

        return $bytes <= $limit ? $bytes : null;
    }
}
