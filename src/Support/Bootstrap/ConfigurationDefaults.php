<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap;

/** Merge settings maps; application lists, empty arrays and scalar values replace defaults. */
final class ConfigurationDefaults
{
    /**
     * @param  array<array-key, mixed>  $defaults
     * @param  array<array-key, mixed>  $overrides
     * @return array<array-key, mixed>
     */
    public static function merge(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $original = $defaults[$key] ?? null;
            $defaults[$key] = is_array($original) && is_array($value)
                && $original !== [] && $value !== []
                && ! array_is_list($original) && ! array_is_list($value)
                    ? self::merge($original, $value)
                    : $value;
        }

        return $defaults;
    }
}
