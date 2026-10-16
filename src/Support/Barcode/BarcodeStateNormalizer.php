<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Barcode;

use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;

final class BarcodeStateNormalizer
{
    public const MAX_BYTES = 4096;

    public static function isValidShape(mixed $state): bool
    {
        if ($state === null) {
            return true;
        }
        if (is_string($state)) {
            return self::isValidText($state);
        }
        if (! is_array($state) || ! array_key_exists('value', $state)
            || array_diff(array_keys($state), ['value', 'format']) !== []
            || ! is_string($state['value']) || ! self::isValidText($state['value'])) {
            return false;
        }
        $format = $state['format'] ?? null;

        return $format === null || (is_string($format) && BarcodeFormat::tryFrom($format) !== null);
    }

    private static function isValidText(string $value): bool
    {
        return strlen($value) <= self::MAX_BYTES && preg_match('//u', $value) === 1 && ! str_contains($value, "\0");
    }

    /** @return array{value: string, format: string|null}|null */
    public static function normalize(mixed $state, bool $storeDetectedFormat = false): ?array
    {
        if ($state === null || ! self::isValidShape($state)) {
            return null;
        }
        $value = trim(is_string($state) ? $state : $state['value']);
        if ($value === '') {
            return null;
        }
        $format = is_array($state) ? ($state['format'] ?? null) : BarcodeValidator::detectFormat($value)?->value;

        return ['value' => $value, 'format' => $storeDetectedFormat ? $format : null];
    }

    public static function extractValue(mixed $state): ?string
    {
        return self::normalize($state)['value'] ?? null;
    }

    public static function extractFormat(mixed $state): ?string
    {
        return self::normalize($state, true)['format'] ?? null;
    }

    /** @return string|array{value: string, format: string|null}|null */
    public static function dehydrate(mixed $state, bool $storeDetectedFormat = false): string|array|null
    {
        $normalized = self::normalize($state, $storeDetectedFormat);

        return $normalized === null ? null : ($storeDetectedFormat ? $normalized : $normalized['value']);
    }

    public static function isEmpty(mixed $state): bool
    {
        return self::isValidShape($state) && self::normalize($state) === null;
    }
}
