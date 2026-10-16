<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Tables\Columns;

use Bjanczak\FilamentFlexFields\Filament\Media\EmitsMediaConsumer;
use Bjanczak\FilamentFlexFields\Filament\Media\UsesPrivateMediaUrls;
use Filament\Tables\Columns\ImageColumn;

class FlexMediaImageColumn extends ImageColumn
{
    use EmitsMediaConsumer;
    use UsesPrivateMediaUrls;

    protected function mediaComponentId(): string
    {
        return 'media-image-column';
    }
}
