<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\DiskUploadBridge;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifest;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\MediaStaging;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Explicit administrative reconciliation; receipts are trusted server data, never browser payloads. */
final class UploadRecovery
{
    public function __construct(private readonly UploadLeaseRepository $leases,
        private readonly UploadStaging $uploads, private readonly MediaStaging $staging, private readonly StageOptions $options, private readonly MediaOs $media) {}

    public function recover(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, string $key, MediaWrite $intent, UploadManifest $reconciled): UploadManifest
    {
        $record = $field->getRecord();
        if ($intent->field !== $field->getName() || $intent->record !== ($record instanceof Model ? $record : null)) {
            throw new MediaFailure('stage_target_mismatch', 'Recovery must preserve the field and record owner.');
        }
        if (DB::connection()->transactionLevel() > 0 || ($intent->record !== null && $intent->record->getConnection()->transactionLevel() > 0)) {
            throw new MediaFailure('stage_recovery_in_transaction', 'Reconcile interrupted storage outside an active record transaction.');
        }
        $owner = $field->getMediaStagingOwner();

        return $this->leases->locked($key, function () use ($field, $key, $intent, $reconciled, $owner): UploadManifest {
            $lease = $this->leases->read($key) ?? throw new MediaFailure('stage_missing', 'Upload binding is unavailable.');
            if (! hash_equals($lease->owner, hash('sha256', $owner))) {
                throw new MediaFailure('denied', 'Upload binding belongs to another form owner.');
            }
            if ($lease->id === null || $lease->removed
                || ! hash_equals($lease->fingerprint, $this->uploads->configurationFingerprint($field, $intent))) {
                throw new MediaFailure('stage_target_mismatch', 'Recovery requires the original active upload configuration.');
            }
            $intent = $lease->intent($intent);
            $result = $reconciled->withContext($intent);
            if ($result->original->operationId !== $lease->id || ($lease->result !== null && $lease->result->encode() !== $result->encode())) {
                throw new MediaFailure('stage_corrupt', 'Reconciled receipt conflicts with the original upload journal.');
            }
            if ($lease->result !== null && $this->staging->status($lease->id, $owner) === 'committed') {
                $this->media->authorize('recover', $intent, $result->original);
                $this->staging->commit($lease->id, $owner, $intent, retainPayload: ! $lease->accepted);

                return $result;
            }
            if ($lease->accepted) {
                throw new MediaFailure('stage_corrupt', 'An accepted upload must have a committed core lease.');
            }
            $restoreMetadata = $field->snapshotUploadMetadata();
            try {
                $this->staging->recover($lease->id, $owner, $intent, $result->original,
                    function () use ($field, $result, $intent, $lease): void {
                        if ($field instanceof FlexSpatieMediaLibraryFileUpload) {
                            app(SpatieUploadBridge::class)->restoreReconciledUpload($field, $result, $intent);
                        } else {
                            app(DiskUploadBridge::class)->restoreReconciledUpload($field, $result, $intent);
                        }
                        $lease->result = $result;
                        $lease->expires = time() + $this->options->receiptTtlSeconds;
                        $this->leases->write($lease);
                    }, retainPayload: true);
            } catch (Throwable $error) {
                $restoreMetadata();
                $failure = $error instanceof MediaFailure ? $error : new MediaFailure('stage_ambiguous', 'Recovery remains incomplete.', $error);
                $failure->cleanupFailed = true;
                throw $failure;
            }
            app(UploadRecordLifecycle::class)->track($field);
            $field->notifyUploadEvent(new UploadEvent(UploadEventType::Recovered, $intent, $result->primaryReceipt()));

            return $result;
        });
    }
}
