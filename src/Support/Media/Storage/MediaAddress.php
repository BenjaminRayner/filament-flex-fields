<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Storage;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;

final class MediaAddress
{
    public static function path(string $value, bool $allowEmpty = false): string
    {
        if (($value === '' && ! $allowEmpty) || strlen($value) > 1024 || preg_match('/[\x00-\x1F\x7F\\\\]/', $value)
            || str_starts_with($value, '/') || str_contains($value, '://') || preg_match('/^[a-zA-Z]:/', $value)) {
            throw new MediaFailure('invalid_path', 'Media destination must be a safe relative path.');
        }
        foreach (explode('/', $value) as $segment) {
            if ($segment === '.' || $segment === '..' || ($segment === '' && $value !== '')) {
                throw new MediaFailure('invalid_path', 'Media destination contains an unsafe segment.');
            }
        }

        return $value;
    }

    public static function filename(string $name): string
    {
        self::path($name);
        if (str_contains($name, '/') || strlen($name) > 255) {
            throw new MediaFailure('invalid_path', 'Media filename must be one path segment.');
        }

        return $name;
    }
}
