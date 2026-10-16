<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Spatie;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadNotifications;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadStaging;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifest;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaReceiptResolver;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaDescriptor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Maps native Filament upload configuration to the optional storage adapter. */
final class SpatieUploadBridge
{
    public function __construct(private readonly MediaOs $media, private readonly OwnedMedia $ownership,
        private readonly UploadStaging $staging, private readonly MediaRegistry $registry) {}

    public function save(FlexSpatieMediaLibraryFileUpload $field, TemporaryUploadedFile $file, ?Model $record = null, bool $stage = true): string
    {
        if ($field->hasFileMetadataEditor()) {
            $field->getFileEditorialState();
        }
        $record ??= $field->getRecord();
        if (! $record instanceof Model || ! $record instanceof HasMedia) {
            throw new MediaFailure('spatie_record_missing', 'Spatie uploads require a persisted HasMedia Eloquent record.');
        }
        $base = $field->makeUploadMediaWrite($file, 'spatie');
        $options = $base->spatie ?? $field->getUploadSpatieOptions($file);
        $intent = new MediaWrite(adapter: 'spatie', kind: $base->kind, disk: $base->disk,
            filename: $base->filename, field: $base->field, record: $record,
            spatie: $options, policy: $base->policy,
            metadata: $base->metadata, processors: $base->processors, operationId: $base->operationId);
        $restoreMetadata = $field->snapshotUploadMetadata();
        $promoted = false;
        try {
            if ($options->collection !== ($field->getCollection() ?? 'default')) {
                throw new InvalidArgumentException('The write must preserve the field collection.');
            }
            if ($field->shouldQueueImageConversions() || $field->getImageConversions() !== []) {
                throw new InvalidArgumentException('Spatie conversions belong to registerMediaConversions on the model.');
            }
            $limit = $record->getMediaCollection($options->collection)?->collectionSizeLimit;
            if ($stage && $field->usesMediaStaging() && $limit && count($field->getRawState() ?? []) > $limit) {
                throw new MediaFailure('collection_file_limit', 'Selected files exceed the native Spatie collection limit.');
            }
            $result = $this->staging->store($field, $file, $intent,
                function (MediaSource $source, MediaWrite $write, Closure $journal) use ($field): MediaReceipt {
                    return $this->media->store($source, $write, static function (MediaReceipt $receipt, MediaWrite $write) use ($field, $journal): void {
                        $field->finalizeUploadMedia($receipt, $write);
                        $field->writeMetadataFromMediaReceipt($receipt);
                        $journal((new UploadManifest($receipt))->withContext($write));
                    });
                },
                function (UploadManifest $result, MediaWrite $write): void {
                    $adapter = $this->registry->adapter('spatie');
                    if (! $adapter instanceof MediaReceiptResolver) {
                        throw new MediaFailure('stage_ambiguous', 'Spatie recovery requires owned receipt restoration.');
                    }
                    $this->media->verify($adapter->restore($result->original, $write), $write);
                },
                fn (UploadManifest $result, MediaWrite $write): bool => $this->media->compensate($result->original, $write), $stage);
            $receipt = $result->original;
            $promoted = true;
            $field->writeMetadataFromMediaReceipt($receipt);
            $field->promoteFileEditorialMetadata($file, $receipt->identifier);
        } catch (\Throwable $error) {
            $restoreMetadata();
            if ($promoted) {
                $error = $error instanceof MediaFailure ? $error : new MediaFailure('upload_finalization_failed', 'Committed upload requires native state reconciliation.', $error);
                $error->cleanupFailed = true;
            }
            $field->notifyUploadEvent(new UploadEvent(
                UploadEventType::Rejected, $intent,
                reason: $error instanceof MediaFailure ? $error->reason : 'validation_or_storage_failed',
                cleanupFailed: $error instanceof MediaFailure && $error->cleanupFailed));
            throw $error;
        }
        $field->notifyStoredUpload($receipt, $intent->identified($receipt->operationId), $file);

        return $receipt->identifier;
    }

    public function find(FlexSpatieMediaLibraryFileUpload $field, string $uuid): ?Media
    {
        $record = $field->getRecord();
        if (! $record instanceof Model || ! $record instanceof HasMedia) {
            return null;
        }
        $media = $this->ownership->find($record, $uuid, $field->getCollection() ?? 'default');
        if ($media === null || ! $field->hasMediaFilter()) {
            return $media;
        }

        // Evaluate the native filter against the complete owned collection, as hydration does.
        return $field->filterMedia($record->getMedia($field->getCollection() ?? 'default'))->contains('uuid', $uuid)
            ? $media : null;
    }

    /** Restore the actual owned Media model; never accept a browser-provided native object. */
    public function restoreReconciledUpload(FlexSpatieMediaLibraryFileUpload $field, UploadManifest $result, MediaWrite $intent): void
    {
        if ($result->original->adapter !== 'spatie' || $result->variants !== [] || $result->primary !== 'original') {
            throw new MediaFailure('adapter_state_incompatible', 'Spatie recovery requires its native original receipt.');
        }
        $adapter = $this->registry->adapter('spatie');
        if (! $adapter instanceof MediaReceiptResolver) {
            throw new MediaFailure('stage_ambiguous', 'Spatie recovery requires owned receipt restoration.');
        }
        $receipt = $adapter->restore($result->original, $intent);
        $this->media->verify($receipt, $intent);
        $field->finalizeUploadMedia($receipt, $intent);
        $field->writeMetadataFromMediaReceipt($receipt);
    }

    /** @return array{name: string, type: string} */
    public function readInteraction(FlexSpatieMediaLibraryFileUpload $field, string $uuid): array
    {
        $media = $this->find($field, $uuid) ?? throw new MediaFailure('denied', 'Media ownership could not be proven.');
        $this->media->authorize('url', $this->readIntent($field, $media), $this->receipt($media));

        return ['name' => (string) ($media->name ?: $media->file_name), 'type' => (string) $media->mime_type];
    }

    public function conversionUrl(FlexSpatieMediaLibraryFileUpload $field, string $uuid, string $name): ?string
    {
        $media = $this->find($field, $uuid) ?? throw new MediaFailure('denied', 'Media ownership could not be proven.');
        $intent = $this->readIntent($field, $media);
        $this->media->authorize('url', $intent, $this->receipt($media));

        return app(SpatieMediaUrls::class)->forMedia($media, $intent->policy->visibility ?? 'private', now()->addMinutes(5), $name, ['field' => $field->getName()]);
    }

    /** @return array{name: string, size: int, type: string, url: ?string, openableUrl: ?string, downloadableUrl: ?string}|null */
    public function payload(FlexSpatieMediaLibraryFileUpload $field, string $uuid): ?array
    {
        $media = $this->find($field, $uuid);
        if ($media === null) {
            return null;
        }
        $intent = $this->readIntent($field, $media);
        $receipt = $this->receipt($media);
        $this->media->authorize('url', $intent, $receipt);
        $preview = $field->getPreviewConversion() ?? $field->getConversion() ?? '';
        $conversion = $preview === '' ? null : ConversionCollection::createForMedia($media)->first(fn ($conversion) => $conversion->getName() === $preview && $conversion->shouldBePerformedOn($media->collection_name));
        $processing = $conversion !== null && $conversion->shouldBeQueued() && ! $media->hasGeneratedConversion($preview) ? 'queued' : null;
        $url = app(SpatieMediaUrls::class)->forMedia($media, $intent->policy === null ? 'private' : $intent->policy->visibility, now()->addMinutes(5),
            $preview, ['field' => $field->getName()]);

        $originalUrl = ($field->isOpenable() || $field->isDownloadable())
            ? app(SpatieMediaUrls::class)->forMedia($media, $intent->policy === null ? 'private' : $intent->policy->visibility, now()->addMinutes(5), '', ['field' => $field->getName()]) : null;

        return ['name' => (string) ($media->name ?: $media->file_name), 'size' => (int) $media->size,
            'type' => (string) $media->mime_type, 'url' => $field->resolveUploadPreviewUrl($uuid, $url),
            'processingStatus' => $processing, 'processingError' => null,
            'openableUrl' => $field->isOpenable() ? $originalUrl : null,
            'downloadableUrl' => $field->isDownloadable() ? $originalUrl : null];
    }

    public function delete(FlexSpatieMediaLibraryFileUpload $field, string $uuid): int
    {
        $media = $this->find($field, $uuid);
        if ($media === null) {
            throw new MediaFailure('denied', 'Spatie media is not owned by this record and collection.');
        }
        $receipt = $this->receipt($media);
        $intent = $this->readIntent($field, $media);
        $leaseKey = $media->getCustomProperty('flex_capture.metadata.flex_upload_lease');
        $this->media->delete($receipt, $intent);
        if (is_string($leaseKey)) {
            try {
                $this->staging->deleted($field, $leaseKey);
            } catch (\Throwable $cleanup) {
                UploadNotifications::report($cleanup);
            }
        }
        $field->removeUploadMetadata($uuid);
        $field->notifyDeletedUpload($receipt, $intent, $receipt->file->bytes);

        return $receipt->file->bytes;
    }

    public function authorizeMetadata(FlexSpatieMediaLibraryFileUpload $field, string $uuid): void
    {
        $media = $this->find($field, $uuid) ?? throw new MediaFailure('denied', 'Media ownership could not be proven.');
        $this->media->authorize('metadata', $this->readIntent($field, $media), $this->receipt($media));
    }

    private function readIntent(FlexSpatieMediaLibraryFileUpload $field, Media $media): MediaWrite
    {
        $base = $field->makeUploadMediaWrite(adapter: 'spatie');

        $policy = $base->policy ?? $field->getMediaPolicy();
        if ($media->getCustomProperty('flex_capture.visibility') === 'private' && $policy->visibility !== 'private') {
            $policy = new MediaPolicy($policy->maxBytes, $policy->minBytes, $policy->mimeTypes,
                $policy->requireScanner, $policy->scanBeforeWrite, $policy->scanAfterWrite, $policy->verifyIntegrity,
                'private', $policy->quarantineDisk);
        }
        $metadata = $media->getCustomProperty('flex_capture.metadata', []);
        $kind = $media->getCustomProperty('flex_capture.kind');
        $operation = $media->getCustomProperty('flex_capture.correlation_id');

        return new MediaWrite(adapter: 'spatie', kind: is_string($kind) ? $kind : $base->kind,
            disk: (string) $media->disk, field: $base->field, record: $base->record,
            policy: $policy, metadata: is_array($metadata) ? $metadata : [],
            operationId: is_string($operation) ? $operation : null,
            spatie: new SpatieOptions(collection: $field->getCollection() ?? 'default'));
    }

    private function receipt(Media $media): MediaReceipt
    {
        $hash = $media->getCustomProperty('flex_capture.sha256');
        $operation = $media->getCustomProperty('flex_capture.correlation_id');
        $width = $media->getCustomProperty('flex_capture.width');
        $height = $media->getCustomProperty('flex_capture.height');

        return new MediaReceipt('spatie', (string) $media->uuid,
            (string) $media->disk, $media->getPathRelativeToRoot(),
            new MediaDescriptor((int) $media->size, (string) $media->mime_type,
                is_string($hash) ? $hash : str_repeat('0', 64), (string) $media->file_name,
                is_int($width) ? $width : null, is_int($height) ? $height : null),
            is_string($operation) ? $operation : 'legacy-'.(string) $media->uuid, $media);
    }
}
