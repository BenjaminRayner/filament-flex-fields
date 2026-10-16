<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Tables\Columns\Spatie;

use Bjanczak\FilamentFlexFields\Filament\Media\EmitsMediaConsumer;
use Bjanczak\FilamentFlexFields\Filament\Media\UsesSpatieMediaUrls;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Illuminate\Database\Eloquent\Model;

/** Optional plugin wrapper: native layout/eager loading/configuration remain native. */
class FlexSpatieMediaLibraryImageColumn extends SpatieMediaLibraryImageColumn
{
    use EmitsMediaConsumer;
    use UsesSpatieMediaUrls;

    protected function mediaComponentId(): string
    {
        return 'spatie-media-image-column';
    }

    protected function mediaRelationshipRecords(Model $record): mixed
    {
        return $this->hasRelationship($record) ? $this->getRelationshipResults($record) : $record;
    }
}
