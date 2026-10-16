<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Video;

use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use InvalidArgumentException;

final class VideoPreviewOptions
{
    public static function source(mixed $source): ?string
    {
        if ($source === null) {
            return null;
        }
        if (! is_string($source) || ($url = SafeMediaUrl::sanitize($source)) === null) {
            throw new InvalidArgumentException('Video preview source must be a supported media URL.');
        }

        return $url;
    }

    /** @return list<array{start: float, end: float, src: string, x: int, y: int, width: ?int, height: ?int}> */
    public static function thumbnails(mixed $configured): array
    {
        if (! is_array($configured) || ! array_is_list($configured) || count($configured) > 2048) {
            throw new InvalidArgumentException('Video preview thumbnails must be a list of at most 2048 frames.');
        }
        $frames = [];
        foreach ($configured as $frame) {
            if (! is_array($frame) || array_diff(array_keys($frame), ['start', 'end', 'src', 'x', 'y', 'width', 'height']) !== []) {
                throw new InvalidArgumentException('Each preview frame requires declared time, source and optional crop coordinates.');
            }
            foreach (['start', 'end'] as $key) {
                if (! is_int($frame[$key] ?? null) && ! is_float($frame[$key] ?? null)) {
                    throw new InvalidArgumentException('Preview frame times must be finite numbers.');
                }
                if (! is_finite((float) $frame[$key]) || $frame[$key] < 0) {
                    throw new InvalidArgumentException('Preview frame times must be finite and nonnegative.');
                }
            }
            if ($frame['end'] <= $frame['start']) {
                throw new InvalidArgumentException('Preview frame end must follow its start.');
            }
            $source = self::source($frame['src'] ?? null);
            if ($source === null) {
                throw new InvalidArgumentException('Each preview frame requires a source URL.');
            }
            $x = self::coordinate($frame['x'] ?? 0, 0);
            $y = self::coordinate($frame['y'] ?? 0, 0);
            $width = isset($frame['width']) ? self::coordinate($frame['width'], 1) : null;
            $height = isset($frame['height']) ? self::coordinate($frame['height'], 1) : null;
            if (($width === null) !== ($height === null) || ($width === null && ($x !== 0 || $y !== 0))) {
                throw new InvalidArgumentException('A preview crop requires both width and height.');
            }
            $frames[] = ['start' => (float) $frame['start'], 'end' => (float) $frame['end'], 'src' => $source, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
        }
        usort($frames, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        $previousEnd = 0.0;
        foreach ($frames as $frame) {
            if ($frame['start'] < $previousEnd) {
                throw new InvalidArgumentException('Preview frame time ranges must not overlap.');
            }
            $previousEnd = $frame['end'];
        }

        return $frames;
    }

    private static function coordinate(mixed $value, int $minimum): int
    {
        if (! is_int($value) || $value < $minimum || $value > 16384) {
            throw new InvalidArgumentException('Preview crop coordinates must be bounded integers.');
        }

        return $value;
    }
}
