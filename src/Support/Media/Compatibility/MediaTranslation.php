<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Compatibility;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaDescriptor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaContext;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaPayload;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\Refs\DiskMediaRef;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\Refs\MediaRef;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\Refs\SpatieMediaRef;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaDefaults;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\OwnedMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieOptions;
use Illuminate\Support\Str;

/** Translation for existing public DTOs; no scanner, storage or lifecycle implementation. */
final class MediaTranslation
{
    public static function intent(MediaContext $context): MediaWrite
    {
        return new MediaWrite(adapter: $context->driver->value, kind: $context->kind->value,
            disk: $context->disk, directory: $context->directory ?? '', filename: $context->filename,
            field: $context->field, record: $context->record,
            spatie: new SpatieOptions(collection: $context->collection ?? 'default', name: $context->mediaName,
                conversionsDisk: $context->conversionsDisk, customProperties: $context->customProperties,
                manipulations: $context->manipulations, attributes: $context->properties,
                headers: $context->customHeaders, responsiveImages: $context->responsiveImages),
            operationId: $context->correlationId ?? (string) Str::uuid());
    }

    public static function source(MediaPayload $payload): MediaSource
    {
        return $payload->source();
    }

    public static function reference(MediaReceipt $receipt): MediaRef
    {
        if ($receipt->adapter === 'disk') {
            return new DiskMediaRef($receipt->disk, $receipt->path);
        }
        if ($receipt->adapter === 'spatie' && $receipt->native !== null) {
            return new SpatieMediaRef($receipt->identifier, $receipt->native, $receipt->disk, $receipt->path);
        }

        throw new MediaFailure('invalid_receipt', 'Legacy API only represents disk and Spatie receipts.');
    }

    public static function receipt(MediaRef $reference, MediaWrite $intent): MediaReceipt
    {
        $native = null;
        if ($reference instanceof SpatieMediaRef) {
            $native = $intent->record === null ? null : app(OwnedMedia::class)->find(
                $intent->record, $reference->uuid, $intent->spatie !== null ? $intent->spatie->collection : 'default');
            if ($native === null) {
                throw new MediaFailure('denied', 'Spatie reference does not belong to this owner and collection.');
            }
        }
        $disk = $native !== null ? (string) $native->disk : $reference->diskName();
        $path = $native !== null ? $native->getPathRelativeToRoot() : $reference->relativePath();
        if ($disk === null || $path === null) {
            throw new MediaFailure('invalid_receipt', 'Legacy media reference has no storage address.');
        }
        $placeholder = new MediaReceipt($reference->driver()->value, $reference->identifier(), $disk, $path,
            new MediaDescriptor(0, 'application/octet-stream', '', basename($path)), (string) $intent->operationId, $native);
        $stream = app(MediaRegistry::class)->adapter($placeholder->adapter)->read($placeholder);
        try {
            $prepared = PreparedMedia::prepare(MediaSource::stream($stream, basename($path)), $intent->policy ?? app(MediaDefaults::class)->policy());
            try {
                return new MediaReceipt($placeholder->adapter, $placeholder->identifier, $disk, $path,
                    $prepared->file, $placeholder->operationId, $native);
            } finally {
                $prepared->close();
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
