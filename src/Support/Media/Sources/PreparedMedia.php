<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Sources;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaDescriptor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use finfo;
use Throwable;

/** Owned disk-backed snapshot; source mutation cannot change inspected bytes. */
final class PreparedMedia
{
    /** @param resource|null $snapshot */
    private function __construct(
        private mixed $snapshot,
        public readonly string $path,
        public readonly MediaDescriptor $file,
    ) {}

    private function __clone() {}

    public static function prepare(MediaSource $source, MediaPolicy $policy): self
    {
        $input = $source->open();
        $snapshot = tmpfile();
        if (! is_resource($snapshot)) {
            if ($source->ownsStream) {
                fclose($input);
            }
            throw new MediaFailure('prepare_failed', 'Unable to allocate media snapshot.');
        }
        try {
            $bytes = 0;
            $hash = hash_init('sha256');
            while (! feof($input)) {
                $chunk = fread($input, max(1, min(65536, $policy->maxBytes - $bytes + 1)));
                if ($chunk === false || ($chunk === '' && ! feof($input))) {
                    throw new MediaFailure('read_failed', 'Media source stopped before completion.');
                }
                $bytes += strlen($chunk);
                if ($bytes > $policy->maxBytes) {
                    throw new MediaFailure('size_rejected', 'Media exceeds its byte limit.');
                }
                if (fwrite($snapshot, $chunk) !== strlen($chunk)) {
                    throw new MediaFailure('prepare_failed', 'Unable to write media snapshot.');
                }
                hash_update($hash, $chunk);
            }
            if (! fflush($snapshot)) {
                throw new MediaFailure('prepare_failed', 'Unable to flush media snapshot.');
            }
            $path = stream_get_meta_data($snapshot)['uri'] ?? null;
            if (! is_string($path) || $path === '') {
                throw new MediaFailure('prepare_failed', 'Media snapshot has no readable local path.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
            if ($mime === 'video/webm' && WebmAudio::isAudioOnly($path)) {
                $mime = 'audio/webm';
            }
            $dimensions = str_starts_with($mime, 'image/') ? @getimagesize($path) : false;
            $descriptor = new MediaDescriptor($bytes, $mime, hash_final($hash), $source->name,
                $dimensions === false ? null : $dimensions[0], $dimensions === false ? null : $dimensions[1]);
            $policy->validate($descriptor);

            return new self($snapshot, $path, $descriptor);
        } catch (Throwable $error) {
            fclose($snapshot);
            throw $error;
        } finally {
            if ($source->ownsStream && is_resource($input)) {
                fclose($input);
            }
        }
    }

    /** @return resource Caller owns this independent cursor. */
    public function open(): mixed
    {
        if (! is_resource($this->snapshot)) {
            throw new MediaFailure('disposed_source', 'Media snapshot has been disposed.');
        }
        $stream = fopen($this->path, 'rb');
        if (! is_resource($stream)) {
            throw new MediaFailure('read_failed', 'Unable to read prepared media.');
        }

        return $stream;
    }

    public function close(): void
    {
        if (is_resource($this->snapshot)) {
            fclose($this->snapshot);
        }
        $this->snapshot = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
