<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images;

enum ImageFit: string
{
    case Contain = 'contain';
    case Cover = 'cover';
    case Pad = 'pad';
    case Stretch = 'stretch';
}
