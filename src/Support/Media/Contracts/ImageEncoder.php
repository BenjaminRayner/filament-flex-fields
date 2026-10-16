<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Contracts;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageLimits;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;

interface ImageEncoder
{
    /** The caller owns the returned snapshot; never mutate or close the original. */
    public function encode(PreparedMedia $original, ImageConversion $conversion, ImageLimits $limits, MediaPolicy $policy): PreparedMedia;
}
