<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Sources;

/** libmagic labels audio-only WebM as video/webm. Inspect bounded EBML track declarations. */
final class WebmAudio
{
    public static function isAudioOnly(string $path): bool
    {
        $header = file_get_contents($path, false, null, 0, 262144);
        if (! is_string($header) || ! str_starts_with($header, "\x1a\x45\xdf\xa3")) {
            return false;
        }
        $offset = 0;
        $ebml = self::element($header, $offset);
        if ($ebml === null || $ebml[2] === null) {
            return false;
        }
        $offset = $ebml[2];
        $segment = self::element($header, $offset);
        if ($segment === null || $segment[0] !== 0x18538067) {
            return false;
        }
        $end = min(strlen($header), $segment[2] ?? strlen($header));
        $audio = false;
        while ($offset < $end) {
            $entry = self::element($header, $offset);
            if ($entry === null) {
                return false;
            }
            if ($entry[0] === 0x1F43B675) {
                return $audio;
            }
            if ($entry[2] === null || $entry[2] > $end) {
                return false;
            }
            if ($entry[0] === 0x1654AE6B) {
                if ($audio || ! self::tracks($header, $offset, $entry[2])) {
                    return false;
                }
                $audio = true;
            }
            $offset = $entry[2];
        }

        return $audio;
    }

    private static function tracks(string $bytes, int $offset, int $end): bool
    {
        $count = 0;
        while ($offset < $end) {
            $entry = self::element($bytes, $offset);
            if ($entry === null || $entry[2] === null || $entry[2] > $end) {
                return false;
            }
            if ($entry[0] === 0xAE) {
                $cursor = $offset;
                $audio = false;
                $codec = false;
                while ($cursor < $entry[2]) {
                    $value = self::element($bytes, $cursor);
                    if ($value === null || $value[2] === null || $value[2] > $entry[2]) {
                        return false;
                    }
                    $data = substr($bytes, $cursor, $value[2] - $cursor);
                    if ($value[0] === 0x83) {
                        if ($audio || $data !== "\x02") {
                            return false;
                        }
                        $audio = true;
                    }
                    if ($value[0] === 0x86) {
                        $codec = in_array($data, ['A_OPUS', 'A_VORBIS'], true);
                        if (! $codec) {
                            return false;
                        }
                    }
                    $cursor = $value[2];
                }
                if (! $audio || ! $codec || ++$count > 8) {
                    return false;
                }
            }
            $offset = $entry[2];
        }

        return $count > 0;
    }

    /** @return array{int, int, int|null}|null ID, payload offset, payload end. */
    private static function element(string $bytes, int &$offset): ?array
    {
        $id = self::integer($bytes, $offset, false, 4);
        $size = self::integer($bytes, $offset, true, 8);
        if ($id === null || $size === null) {
            return null;
        }

        return [$id, $offset, $size === -1 ? null : $offset + $size];
    }

    private static function integer(string $bytes, int &$offset, bool $size, int $maximum): ?int
    {
        if ($offset >= strlen($bytes)) {
            return null;
        }
        $first = ord($bytes[$offset++]);
        $mask = 0x80;
        $length = 1;
        while (($first & $mask) === 0 && $length <= $maximum) {
            $mask >>= 1;
            $length++;
        }
        if ($length > $maximum || $offset + $length - 1 > strlen($bytes)) {
            return null;
        }
        $value = $size ? ($first & ($mask - 1)) : $first;
        $unknown = $size && $value === $mask - 1;
        for ($i = 1; $i < $length; $i++) {
            $next = ord($bytes[$offset++]);
            $unknown = $unknown && $next === 255;
            $value = ($value << 8) | $next;
        }

        return $unknown ? -1 : $value;
    }
}
