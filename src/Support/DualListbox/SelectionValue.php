<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DualListbox;

final class SelectionValue
{
    public static function validShape(mixed $state): bool
    {
        if (! is_array($state) || ! array_is_list($state) || count($state) > 50000) {
            return false;
        }
        foreach ($state as $value) {
            if ((! is_string($value) && ! is_int($value)) || strlen((string) $value) > 512
                || preg_match('//u', (string) $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', (string) $value)) {
                return false;
            }
        }

        return true;
    }

    public static function canonicalize(mixed $state): mixed
    {
        return self::validShape($state) ? array_map(strval(...), $state) : $state;
    }
}
