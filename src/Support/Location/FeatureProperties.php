<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use InvalidArgumentException;

/** Explicit metadata authority shared by GeoJSON ingress and feature forms. */
final class FeatureProperties
{
    public const TEXT_KEYS = ['label', 'description', 'detail', 'value', 'metric', 'tooltip', 'color'];

    public const MAX_TEXT_BYTES = 2048;

    /** @return list<string> */
    public static function extraKeys(mixed $keys): array
    {
        if (! is_array($keys) || ! array_is_list($keys) || count($keys) > 32) {
            throw new InvalidArgumentException('featurePropertyKeys requires up to 32 property names.');
        }
        foreach ($keys as $key) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-zA-Z0-9_]{0,31}$/D', $key)
                || in_array($key, [...self::TEXT_KEYS, 'clickable', 'shape', 'constructor', 'prototype'], true)) {
                throw new InvalidArgumentException('Invalid or reserved custom feature property.');
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<string>  $extraKeys
     * @return array<string, mixed>
     */
    public static function normalize(mixed $properties, array $extraKeys = []): array
    {
        if (! is_array($properties)) {
            throw new InvalidArgumentException('Feature properties must be an object.');
        }
        $safe = [];
        foreach (self::TEXT_KEYS as $key) {
            if (! isset($properties[$key])) {
                continue;
            }
            $text = PickerConfiguration::text($properties[$key], 'feature.'.$key);
            if (strlen($text) > self::MAX_TEXT_BYTES) {
                throw new InvalidArgumentException('Feature text is too long.');
            }
            if ($key === 'color' && ! preg_match('/^#[a-fA-F0-9]{6}$/D', $text)) {
                throw new InvalidArgumentException('Feature color must use #RRGGBB.');
            }
            $safe[$key] = $text;
        }
        if (array_key_exists('clickable', $properties)) {
            if (! is_bool($properties['clickable'])) {
                throw new InvalidArgumentException('Feature clickable must be boolean.');
            }
            $safe['clickable'] = $properties['clickable'];
        }
        foreach ($extraKeys as $key) {
            if (! array_key_exists($key, $properties)) {
                continue;
            }
            $safe[$key] = FeatureMetadata::normalize($properties[$key]);
        }
        if (FeatureMetadata::bytes($safe) > FeatureMetadata::MAX_PROPERTIES_BYTES) {
            throw new InvalidArgumentException('Feature properties exceed their combined JSON byte budget.');
        }

        return $safe;
    }
}
