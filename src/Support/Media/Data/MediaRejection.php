<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Data;

final readonly class MediaRejection
{
    public function __construct(public ?string $quarantinePath, public bool $cleanupFailed) {}
}
