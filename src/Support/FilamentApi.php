<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

/** Bounded immutable framework metadata, independent of records and rendered instances. */
final class FilamentApi
{
    /** @var array<string, bool> */
    private static array $capabilities = [];

    /** @param class-string $class */
    public static function hasMethod(string $class, string $method): bool
    {
        $key = 'method:'.$class.'::'.$method;
        if (array_key_exists($key, self::$capabilities)) {
            return self::$capabilities[$key];
        }
        $supported = method_exists($class, $method);
        if (count(self::$capabilities) < 128) {
            self::$capabilities[$key] = $supported;
        }

        return $supported;
    }

    /** @param class-string $class */
    public static function hasProperty(string $class, string $property): bool
    {
        $key = 'property:'.$class.'::'.$property;
        if (array_key_exists($key, self::$capabilities)) {
            return self::$capabilities[$key];
        }
        $supported = property_exists($class, $property);
        if (count(self::$capabilities) < 128) {
            self::$capabilities[$key] = $supported;
        }

        return $supported;
    }
}
