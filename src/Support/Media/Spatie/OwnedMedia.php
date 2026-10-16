<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Spatie;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class OwnedMedia
{
    public function find(Model $record, string $uuid, string $collection = 'default'): ?Media
    {
        if (! interface_exists(HasMedia::class) || ! $record instanceof HasMedia || ! $record->exists) {
            return null;
        }
        $media = $record->media()->where('uuid', $uuid)->where('collection_name', $collection)->first();

        return $media instanceof Media ? $media : null;
    }
}
