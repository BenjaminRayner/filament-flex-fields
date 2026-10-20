<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Relations;

use InvalidArgumentException;
use JsonException;

/** Bounded JSON data only; never invoke JsonSerializable or retain mutable references. */
final class MediaRelationMetadata
{
    /**
     * Canonical JSON numbers match Eloquent's default array cast: 1.0 becomes 1.
     *
     * @return array<string, mixed>
     */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException('Media metadata must be a JSON object.');
        }
        $entries = 0;
        $safe = self::copy($value, 0, $entries);
        try {
            $json = json_encode($safe, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Media metadata must contain valid UTF-8 JSON.', previous: $error);
        }
        if (strlen($json) > 16384) {
            throw new InvalidArgumentException('Media metadata exceeds 16 KiB.');
        }

        /** @var array<string, mixed> $normalized */
        $normalized = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $normalized;
    }

    /** @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private static function copy(array $value, int $depth, int &$entries): array
    {
        if ($depth > 8 || count($value) > 512) {
            throw new InvalidArgumentException('Media metadata nesting or entry limit exceeded.');
        }
        $safe = [];
        foreach ($value as $key => $item) {
            if (++$entries > 512 || (is_string($key) && (strlen($key) > 128 || preg_match('/[\x00-\x1f\x7f]/', $key)))) {
                throw new InvalidArgumentException('Invalid media metadata key or entry count.');
            }
            if (is_array($item)) {
                $safe[$key] = self::copy($item, $depth + 1, $entries);
            } elseif ($item === null || is_bool($item) || is_int($item) || is_string($item) || (is_float($item) && is_finite($item))) {
                $safe[$key] = $item;
            } else {
                throw new InvalidArgumentException('Media metadata accepts only JSON values.');
            }
        }
        if (! array_is_list($safe)) {
            ksort($safe);
        }

        return $safe;
    }
}
