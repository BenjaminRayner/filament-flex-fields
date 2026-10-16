<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Infolists\Components\Spatie;

use Bjanczak\FilamentFlexFields\Filament\Media\EmitsMediaConsumer;
use Bjanczak\FilamentFlexFields\Filament\Media\UsesSpatieMediaUrls;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Illuminate\Database\Eloquent\Model;

class FlexSpatieMediaLibraryImageEntry extends SpatieMediaLibraryImageEntry
{
    use EmitsMediaConsumer;
    use UsesSpatieMediaUrls;

    protected function mediaComponentId(): string
    {
        return 'spatie-media-image-entry';
    }

    protected function mediaRelationshipRecords(Model $record): mixed
    {
        return $this->hasStateRelationship($record) ? $this->getStateRelationshipResults($record) : $record;
    }
}
