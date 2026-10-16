<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events;

/** Advisory client observations, never persistence receipts or authorization proof. */
final readonly class UploadTransportEvent
{
    public function __construct(public UploadTransportEventType $type, public string $field, public string $session,
        public int $sequence, public ?string $fileId = null, public ?float $progress = null, public ?string $source = null) {}
}
