<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Infolists\Components;

use Bjanczak\FilamentFlexFields\Filament\Media\EmitsMediaConsumer;
use Bjanczak\FilamentFlexFields\Filament\Media\UsesPrivateMediaUrls;
use Filament\Infolists\Components\ImageEntry;

class FlexMediaImageEntry extends ImageEntry
{
    use EmitsMediaConsumer;
    use UsesPrivateMediaUrls;

    protected function mediaComponentId(): string
    {
        return 'media-image-entry';
    }
}
