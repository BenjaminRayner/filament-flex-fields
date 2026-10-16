<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadNotifications;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifest;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\MediaStaging;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageOptions;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/** The native form keeps path/UUID state; all recovery authority stays in server journals. */
final class UploadStaging
{
    public function __construct(
        private readonly MediaStaging $staging,
        private readonly UploadLeaseRepository $leases,
        private readonly StageOptions $options,
        private readonly MediaOs $media,
    ) {}

    /**
     * @param  Closure(MediaSource, MediaWrite, Closure(UploadManifest): void): MediaReceipt  $persist
     * @param  Closure(UploadManifest, MediaWrite): void  $resume
     * @param  Closure(UploadManifest, MediaWrite): bool  $compensate
     */
    public function store(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, TemporaryUploadedFile $file, MediaWrite $intent, Closure $persist, Closure $resume, Closure $compensate, bool $stage = true): UploadManifest
    {
        if (! $stage || ! $field->usesMediaStaging()) {
            $result = null;
            $persist(MediaSource::upload($file), $intent, static function (UploadManifest $manifest) use (&$result): void {
                $result = $manifest;
            });

            return $result ?? throw new MediaFailure('manifest_missing', 'Upload completed without its receipt set.');
        }
        $owner = $field->getMediaStagingOwner();
        $key = hash('sha256', $owner."\0".$file->getFilename());
        $fingerprint = $this->configurationFingerprint($field, $intent);

        return $this->leases->locked($key, function () use ($field, $file, $intent, $persist, $resume, $compensate, $owner, $key, $fingerprint): UploadManifest {
            $lease = $this->leases->read($key) ?? new UploadLease($key, hash('sha256', $owner), $fingerprint, $intent->filename, time() + $this->options->ttlSeconds);
            if (! hash_equals($lease->owner, hash('sha256', $owner)) || ! hash_equals($lease->fingerprint, $fingerprint)) {
                throw new MediaFailure('stage_target_mismatch', 'Upload lease configuration or owner changed.');
            }
            if ($lease->removed) {
                throw new MediaFailure('stage_abandoned', 'This upload was already removed.');
            }
            $intent = $lease->intent($intent);
            if ($lease->id === null) {
                $this->leases->write($lease);
                $staged = $this->staging->stage(MediaSource::upload($file), $intent, $owner);
                $lease->id = $staged->id;
                $lease->expires = $staged->expiresAt;
                $this->leases->write($lease);
            }
            $id = $lease->id ?? throw new MediaFailure('stage_corrupt', 'Upload lease has no media identity.');
            $intent = $intent->identified($id);
            $liveResult = null;
            $promote = function (MediaSource $source, MediaWrite $write, Closure $journal) use ($persist, $lease, &$liveResult): MediaReceipt {
                return $persist($source, $write, function (UploadManifest $result) use ($lease, $journal, &$liveResult): void {
                    $lease->result = $liveResult = $result;
                    $this->leases->write($lease);
                    $journal($result->original);
                });
            };
            $recover = function (MediaReceipt $original, MediaWrite $write) use ($lease, $resume): void {
                $result = $lease->result ?? throw new MediaFailure('stage_ambiguous', 'Interrupted upload has no complete receipt set.');
                if ($original->operationId !== $result->original->operationId || $original->identifier !== $result->original->identifier) {
                    throw new MediaFailure('stage_corrupt', 'Recovery receipt set belongs to another promotion.');
                }
                $resume($result, $write);
            };
            try {
                // The authenticated field journal can reconcile a stopped core journal write, never a guessed destination.
                if ($lease->result !== null && $this->staging->status($id, $owner) === 'promoting') {
                    if (! $field->shouldAutomaticallyRecoverMedia()) {
                        throw new MediaFailure('stage_recovery_required', 'Automatic recovery is disabled; reconcile the interrupted upload explicitly.');
                    }
                    $this->staging->recover($id, $owner, $intent, $lease->result->original, $recover, retainPayload: true);
                } else {
                    $this->staging->commit($id, $owner, $intent, $promote, $recover, retainPayload: true);
                }
            } catch (Throwable $error) {
                if ($error instanceof MediaFailure && ! $error->cleanupFailed && $this->staging->status($id, $owner) === 'pending') {
                    $lease->result = null;
                    $this->leases->write($lease);
                } else {
                    $failure = $error instanceof MediaFailure ? $error : new MediaFailure('stage_ambiguous', 'Upload promotion requires reconciliation.', $error);
                    $failure->cleanupFailed = true;
                    throw $failure;
                }
                throw $error;
            }
            $result = $liveResult ?? $lease->result ?? throw new MediaFailure('manifest_missing', 'Committed upload has no receipt set.');
            $lease->expires = time() + $this->options->receiptTtlSeconds;
            try {
                $this->leases->write($lease);
            } catch (Throwable $error) {
                $failure = new MediaFailure('stage_ambiguous', 'Committed upload requires binding reconciliation.', $error);
                $failure->cleanupFailed = true;
                throw $failure;
            }
            app(UploadRecordLifecycle::class)->track($field);
            $connection = $intent->record?->getConnection() ?? DB::connection();
            if ($connection->transactionLevel() > 0 && method_exists($connection, 'afterRollBack')) {
                $connection->afterRollBack(function () use ($field, $file, $intent, $lease, $result, $owner, $compensate): void {
                    try {
                        $this->leases->locked($lease->key, function () use ($field, $file, $intent, $lease, $result, $owner, $compensate): void {
                            $subject = $field->getRecord();
                            $fresh = $subject instanceof Model ? $subject->fresh() : null;
                            if ($fresh !== null && $field->isMediaUploadReferenced($fresh, $result->primaryReceipt()->identifier)) {
                                // A separate connection or earlier save may still reference the
                                // receipt. Rollback cannot authorize destroying those bytes.
                                return;
                            }
                            $unbindRecord = $intent->record !== null && ! $intent->record->newQuery()->whereKey($intent->record->getKey())->exists();
                            $this->restoreTemporary($file, $lease, $owner, $intent);
                            $this->staging->rollback((string) $lease->id, $owner, $intent,
                                static fn (): bool => $compensate($result, $intent), $unbindRecord);
                            $lease->result = null;
                            $lease->accepted = false;
                            $lease->expires = time() + $this->options->ttlSeconds;
                            $this->leases->write($lease);
                            $raw = $field->getRawState() ?? [];
                            foreach ($raw as $key => $identifier) {
                                if ($identifier === $result->primaryReceipt()->identifier) {
                                    $raw[$key] = $file;
                                }
                            }
                            $field->rawState($raw);
                            $field->restoreFileEditorialMetadata($result->primaryReceipt()->identifier, $file);
                            $field->removeUploadMetadata($result->primaryReceipt()->identifier);
                        });
                    } catch (Throwable $error) {
                        UploadNotifications::report($error);
                    }
                });
            }

            return $result;
        });
    }

    public function accept(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, string $key, Model $record): void
    {
        $owner = $field->getMediaStagingOwner();
        $this->leases->locked($key, function () use ($field, $key, $record, $owner): void {
            $lease = $this->owned($key, $owner);
            $result = $lease->result ?? throw new MediaFailure('stage_ambiguous', 'Upload has not been promoted.');
            if (! $field->isMediaUploadReferenced($record, $result->primaryReceipt()->identifier)) {
                throw new MediaFailure('record_reference_missing', 'Saved record does not reference this upload.');
            }
            if ($lease->id === null) {
                throw new MediaFailure('stage_corrupt', 'Upload lease has no media identity.');
            }
            $base = $field->makeUploadMediaWrite(adapter: $result->original->adapter);
            $intent = new MediaWrite(adapter: $result->original->adapter, kind: $result->context['kind'] ?? $base->kind, disk: $result->original->disk,
                directory: $base->directory, field: $base->field, record: $record, policy: $base->policy,
                metadata: $result->context['metadata'] ?? [], operationId: $lease->id,
                spatie: $field instanceof FlexSpatieMediaLibraryFileUpload ? new SpatieOptions(collection: $field->getCollection() ?? 'default') : null);
            $this->media->authorize('accept', $intent, $result->original);
            if ($lease->accepted) {
                return;
            }
            $this->staging->confirm($lease->id, $owner);
            $lease->accepted = true;
            $lease->expires = time() + $this->options->receiptTtlSeconds;
            $this->leases->write($lease);
            $field->notifyUploadEvent(new UploadEvent(UploadEventType::Accepted, $intent, $result->primaryReceipt()));
        });
    }

    public function abandon(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, string $key): void
    {
        $owner = $field->getMediaStagingOwner();
        $this->leases->locked($key, function () use ($key, $owner): void {
            $lease = $this->owned($key, $owner);
            if ($lease->result !== null) {
                throw new MediaFailure('stage_ambiguous', 'Stored uploads require record reference reconciliation before removal.');
            }
            if ($lease->id !== null) {
                $this->staging->abandon($lease->id, $owner);
            }
            $this->leases->delete($key);
        });
    }

    /** Called only after confirmed removal of the complete receipt set. */
    public function deleted(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, string $key): void
    {
        $owner = $field->getMediaStagingOwner();
        $this->leases->locked($key, function () use ($key, $owner): void {
            $lease = $this->leases->read($key);
            if ($lease === null || ! hash_equals($lease->owner, hash('sha256', $owner))) {
                return;
            }
            if ($lease->id !== null) {
                $this->staging->confirm($lease->id, $owner);
            }
            $lease->removed = true;
            $lease->expires = time();
            $this->leases->write($lease);
        });
    }

    private function owned(string $key, string $owner): UploadLease
    {
        $lease = $this->leases->read($key) ?? throw new MediaFailure('stage_missing', 'Upload lease is unavailable.');
        if (! hash_equals($lease->owner, hash('sha256', $owner))) {
            throw new MediaFailure('denied', 'Upload lease belongs to another form owner.');
        }

        return $lease;
    }

    private function restoreTemporary(TemporaryUploadedFile $file, UploadLease $lease, string $owner, MediaWrite $intent): void
    {
        $this->staging->withSource((string) $lease->id, $owner, $intent, static function (MediaSource $source) use ($file): void {
            $stream = $source->open();
            // The source is borrowed and closed by MediaStaging after this callback.
            if (! Storage::disk(FileUploadConfiguration::disk())->writeStream(FileUploadConfiguration::path($file->getFilename()), $stream, ['visibility' => 'private'])) {
                throw new MediaFailure('stage_write_failed', 'Unable to restore native temporary upload after rollback.');
            }
        });
    }

    public function configurationFingerprint(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, MediaWrite $intent): string
    {
        $spatie = $intent->spatie === null ? null : get_object_vars($intent->spatie);
        if ($spatie !== null) {
            $spatie['configure'] = $intent->spatie?->configure !== null;
            $spatie['sanitizeFilename'] = $intent->spatie?->sanitizeFilename !== null;
        }

        $configuration = [$intent->adapter, $intent->kind, $intent->disk, $intent->directory,
            $intent->field, $intent->record?->getMorphClass(), $intent->policy, $intent->metadata, $intent->processors,
            $spatie, $field->getImageConversions(), $field->getImageLimits(), $field->shouldOptimizeImages(),
            $field->shouldOptimizeImagesToWebp(), $field->shouldOptimizeImagesToAvif(), $field->shouldStripExif(),
            $field->getFlexMaxImageWidth(), $field->getFlexMaxImageHeight()];
        if ($field->shouldQueueImageConversions()) {
            $configuration[] = ['queueImageConversions' => true, 'queue' => $field->getImageConversionQueue()];
        }

        return hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR));
    }
}
