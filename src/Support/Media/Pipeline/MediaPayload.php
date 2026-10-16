<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Pipeline;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaDefaults;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** @deprecated Compatibility value object; preparation belongs to Sources/PreparedMedia. */
final class MediaPayload
{
    private ?PreparedMedia $snapshot = null;

    private function __clone() {}

    /** @param resource|null $stream */
    private function __construct(
        public readonly ?string $localPath,
        public readonly mixed $stream,
        public readonly ?string $inlineContents,
        public readonly ?string $originalName,
        public readonly ?string $mimeType,
        public readonly ?int $size,
    ) {}

    public static function fromTemporaryUploadedFile(TemporaryUploadedFile $file): self
    {
        return self::snapshot(MediaSource::upload($file), app(MediaDefaults::class)->policy());
    }

    public static function fromPath(string $absolutePath, ?string $originalName = null, ?string $mimeType = null, ?int $size = null): self
    {
        return new self($absolutePath, null, null, $originalName ?? basename($absolutePath), $mimeType, $size);
    }

    /** @param resource $stream */
    public static function fromStream(mixed $stream, ?string $originalName = null, ?string $mimeType = null, ?int $size = null, int $maxBytes = 268435456): self
    {
        if ($size !== null && ($size < 0 || $size > $maxBytes)) {
            throw new MediaFailure('size_rejected', 'Declared media size exceeds its byte budget.');
        }

        return self::snapshot(MediaSource::stream($stream, $originalName ?? 'upload.bin'), new MediaPolicy(maxBytes: $maxBytes));
    }

    public static function fromInlineString(string $contents, ?string $originalName = null, ?string $mimeType = null): self
    {
        MediaSource::bytes($contents, $originalName ?? 'capture.bin');

        return new self(null, null, $contents, $originalName, $mimeType, strlen($contents));
    }

    public function source(): MediaSource
    {
        if ($this->localPath !== null) {
            return MediaSource::path($this->localPath, $this->originalName);
        }
        if ($this->inlineContents !== null) {
            return MediaSource::bytes($this->inlineContents, $this->originalName ?? 'upload.bin');
        }
        if (is_resource($this->stream)) {
            return MediaSource::stream($this->stream, $this->originalName ?? 'upload.bin');
        }

        throw new MediaFailure('invalid_source', 'Media payload has no readable source.');
    }

    public function hasLocalPath(): bool
    {
        return $this->localPath !== null && is_file($this->localPath);
    }

    public function scanPath(): ?string
    {
        return $this->hasLocalPath() ? $this->localPath : null;
    }

    /** @return resource */
    public function openReadableStream(): mixed
    {
        return $this->source()->open();
    }

    public function close(): void
    {
        $this->snapshot?->close();
        $this->snapshot = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private static function snapshot(MediaSource $source, MediaPolicy $policy): self
    {
        $file = PreparedMedia::prepare($source, $policy);
        $payload = new self($file->path, null, null, $file->file->originalName, $file->file->mimeType, $file->file->bytes);
        $payload->snapshot = $file;

        return $payload;
    }
}
