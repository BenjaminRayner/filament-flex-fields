<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Contracts;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

interface MediaReceiptResolver
{
    /** Restore adapter-specific native state from its own durable receipt, with owner checks. */
    public function restore(MediaReceipt $receipt, MediaWrite $intent): MediaReceipt;
}
