<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

/** Confirmed server operation, never client progress or a transaction veto. */
final readonly class UploadEvent
{
    public function __construct(
        public UploadEventType $type,
        public MediaWrite $intent,
        public ?MediaReceipt $receipt = null,
        public ?string $reason = null,
        public int $bytesFreed = 0,
        public bool $cleanupFailed = false,
    ) {}
}
