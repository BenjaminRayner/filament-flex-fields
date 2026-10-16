<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Contracts;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;

interface MediaProcessor
{
    /** Return this artifact or a new owned artifact; never close the borrowed input. */
    public function process(PreparedMedia $file, MediaWrite $intent): PreparedMedia;
}
