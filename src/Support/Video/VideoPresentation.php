<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Video;

use InvalidArgumentException;

final class VideoPresentation
{
    public static function aspectRatio(?string $ratio): ?string
    {
        if ($ratio === null || trim($ratio) === '' || trim($ratio) === 'auto') {
            return null;
        }

        $parts = preg_split('/\s*[:\/]\s*/', trim($ratio));

        if ($parts === false || count($parts) > 2) {
            throw new InvalidArgumentException('Video ratios require a positive number or a pair such as 16:9.');
        }

        $values = [];

        foreach ($parts as $part) {
            if (! is_numeric($part) || ! is_finite((float) $part) || (float) $part <= 0) {
                throw new InvalidArgumentException('Video ratio dimensions must be positive finite numbers.');
            }

            $values[] = (float) $part;
        }

        // JSON's numeric representation cannot inject arbitrary CSS declarations.
        return implode(' / ', array_map(static fn (float $value): string => (string) json_encode($value, JSON_THROW_ON_ERROR), $values));
    }
}
