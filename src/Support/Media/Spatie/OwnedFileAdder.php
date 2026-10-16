<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Spatie;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

/** Native FileAdder configuration, with source and operation identity sealed before persistence. */
final class OwnedFileAdder extends FileAdder
{
    private bool $deferCollectionRemoval = false;

    public function seal(PreparedMedia $file, MediaWrite $intent, string $uuid): void
    {
        if ($this->subject !== $intent->record || $this->pathToFile !== $file->path) {
            throw new MediaFailure('spatie_configuration_invalid', 'Spatie configuration cannot replace the media source or owner.');
        }
        // A native callback may replace attributes/custom properties. Preserve its values,
        // then restore the ownership fields needed for precise compensation and recovery.
        $this->properties = array_replace($this->properties, ['uuid' => $uuid]);
        foreach (['id', 'model_id', 'model_type', 'disk', 'conversions_disk', 'file_name', 'size', 'mime_type', 'collection_name', 'custom_properties'] as $reserved) {
            if (array_key_exists($reserved, $this->properties)) {
                throw new MediaFailure('spatie_configuration_invalid', 'Reserved Spatie media attribute: '.$reserved);
            }
        }
        $this->preserveOriginal = true;
        $this->deferCollectionRemoval = isset($intent->metadata['flex_upload_lease']);
        $this->customProperties['flex_capture'] = array_replace(
            is_array($this->customProperties['flex_capture'] ?? null) ? $this->customProperties['flex_capture'] : [],
            ['field' => $intent->field, 'kind' => $intent->kind,
                'collection' => $intent->spatie !== null ? $intent->spatie->collection : 'default', 'correlation_id' => $intent->operationId,
                'sha256' => $file->file->sha256, 'width' => $file->file->width, 'height' => $file->file->height,
                'metadata' => $intent->metadata, 'visibility' => $intent->policy === null ? 'private' : $intent->policy->visibility],
        );
    }

    protected function getMediaCollection(string $collectionName): ?MediaCollection
    {
        $collection = parent::getMediaCollection($collectionName);
        if ($collection !== null && $this->deferCollectionRemoval) {
            // Preserve native acceptance, disks and conversions. The field reconciles the
            // collection after DB commit; eager singleFile deletion cannot be rolled back.
            $collection = clone $collection;
            $collection->collectionSizeLimit = false;
        }

        return $collection;
    }
}
