<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Data;

/** The adapter owns deletion/read semantics; a receipt is not an authorization token. */
final readonly class MediaReceipt
{
    public function __construct(
        public string $adapter,
        public string $identifier,
        public string $disk,
        public string $path,
        public MediaDescriptor $file,
        public string $operationId,
        public ?object $native = null,
    ) {}
}
