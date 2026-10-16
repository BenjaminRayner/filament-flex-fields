<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Contracts;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;

interface MediaScanner
{
    /** Throw MediaFailure for rejection; returning normally certifies clean bytes. */
    public function scan(PreparedMedia $file, MediaWrite $intent, string $stage): void;
}
