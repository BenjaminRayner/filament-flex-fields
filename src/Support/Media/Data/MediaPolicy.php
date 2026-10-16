<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Data;

use InvalidArgumentException;

final readonly class MediaPolicy
{
    /** @param list<string> $mimeTypes */
    public function __construct(
        public int $maxBytes = 268435456,
        public int $minBytes = 0,
        public array $mimeTypes = [],
        public bool $requireScanner = false,
        public bool $scanBeforeWrite = true,
        public bool $scanAfterWrite = true,
        public bool $verifyIntegrity = true,
        public string $visibility = 'private',
        public ?string $quarantineDisk = null,
    ) {
        if ($maxBytes < 1 || $maxBytes >= PHP_INT_MAX || $minBytes < 0 || $minBytes > $maxBytes) {
            throw new InvalidArgumentException('Invalid Media OS byte limits.');
        }
        if (! in_array($visibility, ['private', 'public'], true)) {
            throw new InvalidArgumentException('Media visibility must be private or public.');
        }
        foreach ($mimeTypes as $mime) {
            if (! is_string($mime) || ! preg_match('~^[a-z0-9.+-]+/(?:[a-z0-9.+-]+|\*)$~D', $mime)) {
                throw new InvalidArgumentException('Invalid Media OS MIME allow-list.');
            }
        }
        if ($requireScanner && (! $scanBeforeWrite || ! $scanAfterWrite)) {
            throw new InvalidArgumentException('Required scanning cannot be disabled at either boundary.');
        }
    }

    public function validate(MediaDescriptor $file): void
    {
        if ($file->bytes < $this->minBytes || $file->bytes > $this->maxBytes) {
            throw new MediaFailure('size_rejected', 'Media does not meet its byte limits.');
        }
        if ($this->mimeTypes === []) {
            return;
        }
        foreach ($this->mimeTypes as $accepted) {
            if ($file->mimeType === $accepted || (str_ends_with($accepted, '/*') && str_starts_with($file->mimeType, substr($accepted, 0, -1)))) {
                return;
            }
        }
        throw new MediaFailure('type_rejected', 'Inspected media type is not allowed.');
    }
}
