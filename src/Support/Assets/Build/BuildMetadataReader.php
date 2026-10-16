<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Build;

use JsonException;
use RuntimeException;

final class BuildMetadataReader
{
    public const MAX_BYTES = 4 * 1024 * 1024;

    /** @return array<string, mixed> */
    public static function read(string $path): array
    {
        $json = is_file($path) && is_readable($path) && ! is_link($path)
            ? file_get_contents($path, false, null, 0, self::MAX_BYTES + 1) : false;
        if (! is_string($json) || strlen($json) > self::MAX_BYTES) {
            throw new RuntimeException('Missing, unsafe or oversized build metadata: '.$path);
        }
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid JSON build metadata: '.$path, previous: $exception);
        }
        if (! is_array($data) || array_is_list($data)) {
            throw new RuntimeException('Build metadata must be an object: '.$path);
        }

        return $data;
    }
}
