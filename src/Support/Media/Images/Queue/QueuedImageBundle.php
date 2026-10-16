<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images\Queue;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;

/** The original is immediately available; variants belong to the journal and worker. */
final readonly class QueuedImageBundle
{
    public function __construct(public string $id, public MediaReceipt $original) {}
}
