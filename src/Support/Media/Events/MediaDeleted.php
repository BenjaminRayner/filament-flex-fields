<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Events;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

/** Notification after removal; a listener cannot turn a completed deletion into a failed operation. */
final readonly class MediaDeleted
{
    public function __construct(public MediaReceipt $receipt, public MediaWrite $intent) {}
}
