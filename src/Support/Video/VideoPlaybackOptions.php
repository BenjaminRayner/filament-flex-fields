<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Video;

use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Bjanczak\FilamentFlexFields\Support\Translations;
use InvalidArgumentException;

/** Value normalization without retaining component state or evaluating closures. */
final class VideoPlaybackOptions
{
    private const MAX_OPTIONS = 64;

    /** @return list<float>|null */
    public static function rates(mixed $configured): ?array
    {
        if ($configured === null || $configured === false) {
            return null;
        }

        if ($configured === true) {
            return [0.25, 0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0];
        }

        if (! is_array($configured) || count($configured) > self::MAX_OPTIONS) {
            throw new InvalidArgumentException('Video playback rates must be an array of at most 64 positive finite numbers.');
        }

        $rates = [];

        foreach ($configured as $rate) {
            if (! is_numeric($rate) || ! is_finite((float) $rate) || (float) $rate <= 0) {
                throw new InvalidArgumentException('Video playback rates must be positive finite numbers.');
            }

            $rates[] = (float) $rate;
        }

        sort($rates, SORT_NUMERIC);
        $rates = array_values(array_unique($rates, SORT_REGULAR));

        return $rates !== [] ? $rates : null;
    }

    /** @return list<array{key: string, label: string, src: string, height: ?int, default: bool}> */
    public static function qualities(mixed $configured): array
    {
        if ($configured === null) {
            return [];
        }

        if (! is_array($configured) || count($configured) > self::MAX_OPTIONS) {
            throw new InvalidArgumentException('Video quality options must be an array of at most 64 entries.');
        }

        $options = [];
        $keys = [];
        $hasDefault = false;

        foreach (array_values($configured) as $index => $option) {
            if (! is_array($option) || ! is_string($option['src'] ?? null)) {
                throw new InvalidArgumentException('Each video quality option requires a string source URL.');
            }

            $src = SafeMediaUrl::sanitize($option['src']);

            if ($src === null) {
                throw new InvalidArgumentException('A video quality option contains an unsupported source URL.');
            }

            $key = $option['key'] ?? 'quality-'.$index;

            if ((! is_string($key) && ! is_int($key)) || (string) $key === '' || strlen((string) $key) > 128 || preg_match('/[\x00-\x1F\x7F]/', (string) $key)) {
                throw new InvalidArgumentException('Video quality keys must be nonempty strings or integers, at most 128 bytes.');
            }

            $key = (string) $key;

            if (isset($keys[$key])) {
                throw new InvalidArgumentException("Duplicate video quality key [{$key}].");
            }

            $keys[$key] = true;
            $height = $option['height'] ?? null;

            if ($height !== null) {
                $height = filter_var($height, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

                if ($height === false) {
                    throw new InvalidArgumentException('Video quality heights must be positive integers.');
                }
            }

            $label = $option['label'] ?? null;

            if ($label === null || $label === '') {
                $label = Translations::get('filament-flex-fields::default.video.quality_option', ['height' => $height ?? $index + 1]);
            } elseif (! is_string($label) && ! is_int($label)) {
                throw new InvalidArgumentException('Video quality labels must be strings or integers.');
            }

            $default = $option['default'] ?? false;

            if (! is_bool($default) || ($default && $hasDefault)) {
                throw new InvalidArgumentException('Video quality defaults must be booleans, with at most one default.');
            }

            $hasDefault = $hasDefault || $default;
            $options[] = ['key' => $key, 'label' => (string) $label, 'src' => $src, 'height' => $height, 'default' => $default];
        }

        if ($options !== [] && ! $hasDefault) {
            $options[0]['default'] = true;
        }

        return $options;
    }
}
