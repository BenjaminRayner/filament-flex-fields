<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Translatable;

final class SpatieTranslatableState
{
    /** Filament's field owns dehydration; preserve its types and whitespace. */
    public static function dehydrate(mixed $state): mixed
    {
        return $state;
    }

    public static function hydrate(mixed $translation): mixed
    {
        if (is_string($translation)) {
            $decoded = json_decode($translation, true);
            if (is_array($decoded) && self::looksLikeTipTapDocument($decoded)) {
                return $decoded;
            }
        }

        return $translation;
    }

    /** @param array<mixed> $value */
    public static function looksLikeTipTapDocument(array $value): bool
    {
        return ($value['type'] ?? null) === 'doc' && array_key_exists('content', $value);
    }

    /** @return array<string, mixed>|null */
    public static function translations(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (! is_array($value) || ($value !== [] && array_is_list($value)) || self::looksLikeTipTapDocument($value)) {
            return null;
        }
        foreach (array_keys($value) as $locale) {
            if (! is_string($locale)) {
                return null;
            }
        }

        return $value;
    }
}
