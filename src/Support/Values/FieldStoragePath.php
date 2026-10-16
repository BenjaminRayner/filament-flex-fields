<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values;

use InvalidArgumentException;

/** Literal Laravel dotted paths; never accepts wildcard or empty segments. */
final class FieldStoragePath
{
    public static function assert(string $path): void
    {
        if (strlen($path) > 1024 || substr_count($path, '.') > 59 || preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/D', $path) !== 1) {
            throw new InvalidArgumentException('Flex field storage paths must be nonempty literal dotted paths without wildcards.');
        }
        foreach (explode('.', $path) as $segment) {
            if (in_array($segment, ['__proto__', 'constructor', 'prototype'], true)) {
                throw new InvalidArgumentException('Flex field storage paths cannot contain reserved object-property segments.');
            }
        }
    }
}
