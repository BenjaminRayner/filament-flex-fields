<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use InvalidArgumentException;

/** Literal schema data is checked before configurators perform PHP casts. */
final class ConfigurationRules
{
    /** @param array<string, mixed> $config */
    public static function integer(array $config, string $key, int $min = 0, int $max = 2147483647): void
    {
        if (! isset($config[$key])) {
            return;
        }
        $value = $config[$key];
        if ((! is_int($value) && (! is_string($value) || preg_match('/^-?[0-9]+$/D', $value) !== 1))
            || (float) $value < $min || (float) $value > $max) {
            self::fail($key, 'must be an integer between '.$min.' and '.$max.' or null');
        }
    }

    /** @param array<string, mixed> $config */
    public static function number(array $config, string $key, ?float $min = null, ?float $max = null): void
    {
        if (! isset($config[$key])) {
            return;
        }
        $value = $config[$key];
        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! is_numeric($value)
            || ! is_finite((float) $value) || ($min !== null && (float) $value < $min) || ($max !== null && (float) $value > $max)) {
            self::fail($key, 'must be a finite number within its declared bounds or null');
        }
    }

    /** @param array<string, mixed> $config */
    public static function text(array $config, string $key, int $max = 65536, ?string $pattern = null): void
    {
        if (! isset($config[$key])) {
            return;
        }
        $value = $config[$key];
        if (! is_string($value) || strlen($value) > $max || str_contains($value, "\0")
            || ($pattern !== null && preg_match($pattern, $value) !== 1)) {
            self::fail($key, 'must be valid bounded text or null');
        }
    }

    /** @param array<string, mixed> $config */
    public static function boolean(array $config, string $key): void
    {
        if (array_key_exists($key, $config) && ! is_bool($config[$key])) {
            self::fail($key, 'must be a boolean');
        }
    }

    /** @param array<string, mixed> $config */
    public static function array(array $config, string $key): void
    {
        if (isset($config[$key]) && ! is_array($config[$key])) {
            self::fail($key, 'must be an array or null');
        }
    }

    /** @param array<string, mixed> $config */
    public static function nonNull(array $config, string $key): void
    {
        if (array_key_exists($key, $config) && $config[$key] === null) {
            self::fail($key, 'does not accept explicit null; omit the setting to use its default');
        }
    }

    /**
     * A documented boolean switch can also carry a bounded page/batch size.
     *
     * @param  array<string, mixed>  $config
     */
    public static function toggleOrCount(array $config, string $key): void
    {
        self::nonNull($config, $key);
        if (! isset($config[$key]) || is_bool($config[$key])) {
            return;
        }
        if (! is_int($config[$key]) || $config[$key] < 1 || $config[$key] > 4096) {
            self::fail($key, 'must be a boolean or an integer between 1 and 4096');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<int|string>  $choices
     */
    public static function choice(array $config, string $key, array $choices): void
    {
        if (isset($config[$key]) && ! in_array($config[$key], $choices, true)) {
            self::fail($key, 'has an unsupported value');
        }
    }

    /** @param array<string, mixed> $config */
    public static function ordered(array $config, string $min, string $max, ?string $exact = null): void
    {
        if (isset($config[$min], $config[$max]) && (float) $config[$min] > (float) $config[$max]) {
            self::fail($min, 'must not exceed '.$max);
        }
        if ($exact !== null && isset($config[$exact])
            && ((isset($config[$min]) && (float) $config[$exact] < (float) $config[$min])
                || (isset($config[$max]) && (float) $config[$exact] > (float) $config[$max]))) {
            self::fail($exact, 'must respect '.$min.' and '.$max);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<mixed>|null
     */
    public static function items(array $config, string $key, int $max = 4096): ?array
    {
        if (! isset($config[$key])) {
            return null;
        }
        $value = $config[$key];
        if (! is_array($value) || ! array_is_list($value) || count($value) > $max) {
            self::fail($key, 'must be a bounded list or null');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<int|string>|null  $choices
     */
    public static function identifiers(array $config, string $key, ?array $choices = null, int $max = 4096, ?string $pattern = null): void
    {
        foreach (self::items($config, $key, $max) ?? [] as $value) {
            if ((! is_string($value) && ! is_int($value)) || (is_string($value) && (strlen($value) > 512 || str_contains($value, "\0")))
                || ($choices !== null && ! in_array($value, $choices, true))
                || ($pattern !== null && (! is_string($value) || preg_match($pattern, $value) !== 1))) {
                self::fail($key, 'contains an invalid identifier');
            }
        }
    }

    public static function fail(string $key, string $reason): never
    {
        throw new InvalidArgumentException('Configuration '.$key.' '.$reason.'.');
    }
}
