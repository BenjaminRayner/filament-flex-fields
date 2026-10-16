<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use InvalidArgumentException;

/** Bounded JSON values support native multiple selections and repeaters without accepting arbitrary objects. */
final class FeatureMetadata
{
    public const MAX_PROPERTY_BYTES = 16384;

    public const MAX_PROPERTIES_BYTES = 65536;

    public static function normalize(mixed $value): mixed
    {
        $nodes = 0;
        self::validate($value, 0, $nodes);
        if (self::bytes($value) > self::MAX_PROPERTY_BYTES) {
            throw new InvalidArgumentException('Custom feature property exceeds its JSON byte budget.');
        }

        return $value;
    }

    public static function bytes(mixed $value): int
    {
        return strlen(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS));
    }

    private static function validate(mixed $value, int $depth, int &$nodes): void
    {
        if ($depth > 6 || ++$nodes > 256) {
            throw new InvalidArgumentException('Custom feature property exceeds its structural budget.');
        }
        if (is_string($value)) {
            PickerConfiguration::text($value, 'feature metadata');
            if (strlen($value) > FeatureProperties::MAX_TEXT_BYTES) {
                throw new InvalidArgumentException('Custom feature text is too long.');
            }

            return;
        }
        if (is_array($value)) {
            if (count($value) > 100) {
                throw new InvalidArgumentException('Custom feature array is too large.');
            }
            $list = array_is_list($value);
            foreach ($value as $key => $item) {
                if (! $list && (! is_string($key) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $key)
                    || in_array($key, ['constructor', 'prototype'], true))) {
                    throw new InvalidArgumentException('Invalid nested feature property key.');
                }
                self::validate($item, $depth + 1, $nodes);
            }

            return;
        }
        if ($value !== null && ! is_bool($value) && ! is_int($value) && ! (is_float($value) && is_finite($value))) {
            throw new InvalidArgumentException('Custom feature metadata must contain JSON values.');
        }
    }
}
