<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\BubbleChoice;

final class SelectionValue
{
    public static function validShape(mixed $state): bool
    {
        if (! is_array($state) || ! array_is_list($state) || count($state) > OptionCatalog::LIMIT) {
            return false;
        }
        foreach ($state as $key) {
            if ((! is_string($key) && ! is_int($key)) || ! OptionCatalog::validKey((string) $key)) {
                return false;
            }
        }

        return true;
    }

    public static function canonicalize(mixed $state): mixed
    {
        return self::validShape($state) ? array_map(strval(...), $state) : $state;
    }

    /** @param array<string|int, array{disabled: bool}> $options
     * @return list<string> */
    public static function preview(mixed $state, array $options): array
    {
        if (! self::validShape($state)) {
            return [];
        }
        $seen = [];
        foreach ($state as $key) {
            $key = (string) $key;
            if (isset($options[$key]) && ! $options[$key]['disabled']) {
                $seen[$key] = $key;
            }
        }

        return array_values($seen);
    }
}
