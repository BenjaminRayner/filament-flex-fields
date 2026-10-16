<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\DiskUploadBridge;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadNotifications;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadLeaseRepository;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadStaging;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifestRepository;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Throwable;
use WeakMap;

/** Request-scoped participation, independent for every rendered upload field. */
final class UploadRecordLifecycle
{
    /** @var WeakMap<FlexFileUpload|FlexSpatieMediaLibraryFileUpload, bool> */
    private WeakMap $fields;

    public function __construct()
    {
        $this->fields = new WeakMap;
    }

    public function track(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field): void
    {
        $this->fields[$field] = true;
    }

    public function acceptFor(Component $owner, Model $record): void
    {
        foreach ($this->fields as $field => $active) {
            try {
                $fieldOwner = $field->getLivewire();
            } catch (\TypeError) {
                // Server-only schemas are accepted explicitly by their own save integration.
                continue;
            }
            if ($fieldOwner !== $owner) {
                continue;
            }
            $subject = $field->getRecord();
            if ($subject instanceof Model && $subject->exists && ! $subject->is($record)) {
                continue;
            }
            $this->acceptField($field, $record);
        }
    }

    /** Native actions also save Eloquent models without dispatching Resource RecordSaved. */
    public function acceptForRecord(Model $record): void
    {
        foreach ($this->fields as $field => $active) {
            $subject = $field->getRecord();
            if ($subject instanceof Model && $subject->exists && $subject->is($record)) {
                $this->acceptIfReferenced($field, $record);
            }
        }
    }

    /** Relationship hooks can run before an edit persists disk state; that is not a failed save. */
    public function acceptIfReferenced(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, Model $record): void
    {
        $fresh = $record->fresh();
        if ($fresh === null) {
            return;
        }
        foreach ($field->getRawState() ?? [] as $identifier) {
            if (! is_string($identifier) || ! $field->isMediaUploadReferenced($fresh, $identifier)) {
                return;
            }
        }
        if (! $field instanceof FlexSpatieMediaLibraryFileUpload) {
            foreach (array_diff($field->getBaselineFilePaths(), array_values($field->getRawState() ?? [])) as $removed) {
                if ($field->isMediaUploadReferenced($fresh, $removed)) {
                    return;
                }
            }
        }
        $this->acceptField($field, $record);
    }

    /** Explicit hook for custom saves; the callback executes only after the enclosing transaction. */
    public function acceptField(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, Model $record): void
    {
        $complete = function () use ($field, $record): void {
            try {
                $fresh = $record->fresh() ?? throw new MediaFailure('record_missing', 'Saved upload record is unavailable.');
                foreach ($field->getRawState() ?? [] as $identifier) {
                    if (! is_string($identifier)) {
                        continue;
                    }
                    if (! $field->isMediaUploadReferenced($fresh, $identifier)) {
                        throw new MediaFailure('record_reference_missing', 'Stored record does not reference the submitted file state.');
                    }
                }
                // A mutated save can retain an old reference elsewhere in JSON; never delete it.
                if (! $field instanceof FlexSpatieMediaLibraryFileUpload) {
                    foreach (array_diff($field->getBaselineFilePaths(), array_values($field->getRawState() ?? [])) as $removed) {
                        if ($field->isMediaUploadReferenced($fresh, $removed)) {
                            throw new MediaFailure('record_reference_remaining', 'A replaced file remains referenced by the record.');
                        }
                    }
                }
                // Prove every reference before persisting metadata or accepting any staged source.
                $field->syncFileEditorialMetadata();
                foreach ($field->getRawState() ?? [] as $identifier) {
                    if (! is_string($identifier)) {
                        continue;
                    }
                    $key = $this->leaseKey($field, $identifier);
                    if ($key !== null) {
                        $lease = app(UploadLeaseRepository::class)->read($key);
                        if ($lease !== null && ! $lease->accepted && hash_equals($lease->owner, hash('sha256', $field->getMediaStagingOwner()))) {
                            app(UploadStaging::class)->accept($field, $key, $fresh);
                        }
                    }
                }
                if ($field instanceof FlexFileUpload) {
                    foreach ($field->getRawState() ?? [] as $identifier) {
                        if (is_string($identifier)) {
                            app(DiskUploadBridge::class)->dispatchConversions($field, $identifier, $fresh);
                        }
                    }
                }
                $field->cleanupReplacedFilesAfterSave();
            } catch (Throwable $error) {
                // DB may already be committed. Retain journals/cleanup for reconciliation, never fake rollback.
                UploadNotifications::report($error);
            }
        };
        $connection = $record->getConnection();
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit($complete);
        } else {
            $complete();
        }
    }

    private function leaseKey(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, string $identifier): ?string
    {
        if ($field instanceof FlexSpatieMediaLibraryFileUpload) {
            $key = app(SpatieUploadBridge::class)->find($field, $identifier)?->getCustomProperty('flex_capture.metadata.flex_upload_lease');
        } else {
            $key = app(UploadManifestRepository::class)->read($field->getDiskName(), $identifier)?->context['metadata']['flex_upload_lease'] ?? null;
        }

        return is_string($key) ? $key : null;
    }
}
