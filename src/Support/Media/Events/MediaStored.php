<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Events;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

/** Committed notification; listener failure does not undo media or other listener side effects. */
final readonly class MediaStored
{
    public function __construct(public MediaReceipt $receipt, public MediaWrite $intent) {}
}
