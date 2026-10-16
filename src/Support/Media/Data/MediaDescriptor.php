<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Data;

final readonly class MediaDescriptor
{
    public function __construct(
        public int $bytes,
        public string $mimeType,
        public string $sha256,
        public string $originalName,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
