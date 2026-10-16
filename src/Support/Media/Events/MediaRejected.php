<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Events;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

final readonly class MediaRejected
{
    public function __construct(public MediaWrite $intent, public string $reason, public bool $cleanupFailed) {}
}
