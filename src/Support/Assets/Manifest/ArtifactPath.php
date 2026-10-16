<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Manifest;

use InvalidArgumentException;
use RuntimeException;

/** Only relative build outputs under an explicitly chosen local root. */
final class ArtifactPath
{
    public static function assert(string $path): void
    {
        if (strlen($path) > 512 || preg_match('~^[a-zA-Z0-9_-][a-zA-Z0-9_.-]*(?:/[a-zA-Z0-9_-][a-zA-Z0-9_.-]*)*$~D', $path) !== 1) {
            throw new InvalidArgumentException('Invalid relative artifact path: '.$path);
        }
        foreach (explode('/', $path) as $segment) {
            if (str_ends_with($segment, '.') || preg_match('/^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/iD', $segment) === 1) {
                throw new InvalidArgumentException('Artifact path is not portable: '.$path);
            }
        }
    }

    public static function root(string $root): string
    {
        $resolved = realpath($root);
        if ($resolved === false || ! is_dir($resolved) || dirname($resolved) === $resolved) {
            throw new RuntimeException('Cannot resolve the artifact root.');
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    /** A symlink inside the chosen root is rejected even when it points inside it. */
    public static function source(string $root, string $path): string
    {
        self::assert($path);
        $candidate = $root;
        foreach (explode('/', $path) as $segment) {
            $candidate .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($candidate)) {
                throw new RuntimeException('Symlink in artifact source: '.$path);
            }
        }
        $resolved = realpath($candidate);
        if ($resolved === false || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new RuntimeException('Missing or unsafe artifact source: '.$path);
        }

        return $resolved;
    }
}
