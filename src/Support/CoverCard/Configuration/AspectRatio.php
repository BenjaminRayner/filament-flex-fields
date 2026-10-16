<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CoverCard\Configuration;

use InvalidArgumentException;

final class AspectRatio
{
    public static function resolve(?string $ratio): ?string
    {
        if ($ratio === null) {
            return null;
        }
        $ratio = trim($ratio);
        if ($ratio === '' || $ratio === 'auto') {
            return null;
        }
        $parts = preg_split('/[:\/]/', $ratio);
        if ($parts === false || count($parts) > 2 || strlen($ratio) > 128) {
            throw new InvalidArgumentException('Invalid Cover Card aspect ratio.');
        }
        foreach ($parts as &$part) {
            $part = trim($part);
            if (! is_numeric($part) || ! is_finite((float) $part) || (float) $part <= 0) {
                throw new InvalidArgumentException('Cover Card aspect ratio dimensions must be finite positive numbers.');
            }
        }
        unset($part);

        return implode(' / ', $parts);
    }
}
