<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Events;

use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageBundle;

final readonly class ImageBundleStored
{
    public function __construct(public ImageBundle $bundle) {}
}
