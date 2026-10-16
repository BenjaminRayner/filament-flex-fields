<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Fonts;

use InvalidArgumentException;

/** Signed continuations bind the entire field policy, catalog revision and query. */
final class FontPage
{
    public static function offset(?string $cursor, string $context): int
    {
        if ($cursor === null) {
            return 0;
        }
        if (strlen($cursor) > 256 || ! preg_match('/^([1-9][0-9]{0,4}):([a-f0-9]{64})$/D', $cursor, $parts)
            || (int) $parts[1] > 10000 || ! hash_equals(self::signature((int) $parts[1], $context), $parts[2])) {
            throw new InvalidArgumentException('Invalid font continuation.');
        }

        return (int) $parts[1];
    }

    public static function cursor(int $offset, string $context): string
    {
        return $offset.':'.self::signature($offset, $context);
    }

    private static function signature(int $offset, string $context): string
    {
        return hash_hmac('sha256', $offset.':'.$context, (string) config('app.key'));
    }
}
