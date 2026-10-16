<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Sources;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Closure;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final readonly class MediaSource
{
    /** @param Closure(): mixed $open */
    private function __construct(
        private Closure $open,
        public string $name,
        public bool $ownsStream,
    ) {
        if ($name === '' || strlen($name) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $name) || preg_match('//u', $name) !== 1) {
            throw new MediaFailure('invalid_source', 'Media source name must be bounded, valid text.');
        }
    }

    public static function path(string $path, ?string $name = null): self
    {
        return new self(static fn () => is_file($path) ? fopen($path, 'rb') : false, $name ?? basename($path), true);
    }

    /** A borrowed stream is read from its current position and never closed or rewound. @param resource $stream */
    public static function stream(mixed $stream, string $name = 'upload.bin'): self
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new MediaFailure('invalid_source', 'Media source must be a readable stream.');
        }

        return new self(static fn () => $stream, $name, false);
    }

    /** Inline capture has a separate 4 MiB ceiling; use streams for larger media. */
    public static function bytes(string $bytes, string $name = 'capture.bin'): self
    {
        if (strlen($bytes) > 4194304) {
            throw new MediaFailure('size_rejected', 'Inline media exceeds the capture limit.');
        }

        return new self(static function () use ($bytes) {
            $stream = fopen('php://temp/maxmemory:65536', 'w+b');
            if (! is_resource($stream)) {
                throw new MediaFailure('invalid_source', 'Unable to prepare inline media.');
            }
            if (fwrite($stream, $bytes) !== strlen($bytes) || ! rewind($stream)) {
                fclose($stream);
                throw new MediaFailure('invalid_source', 'Unable to prepare inline media.');
            }

            return $stream;
        }, $name, true);
    }

    public static function upload(TemporaryUploadedFile $file): self
    {
        return new self(static fn () => $file->readStream(), $file->getClientOriginalName(), true);
    }

    /** @return resource */
    public function open(): mixed
    {
        $stream = ($this->open)();
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new MediaFailure('invalid_source', 'Unable to read media source.');
        }

        return $stream;
    }
}
