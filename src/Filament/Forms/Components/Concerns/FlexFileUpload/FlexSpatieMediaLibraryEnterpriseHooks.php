<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\UploadAdmission;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\HasMedia;

/**
 * Spatie-safe enterprise hooks — never replaces Spatie disk persistence with FlexFileUpload paths.
 *
 * @mixin BaseFileUpload
 */
trait FlexSpatieMediaLibraryEnterpriseHooks
{
    public function registerFlexSpatieMediaLibraryEnterpriseHooks(): void
    {
        $this->applyMediaCaptureStorageDefaults();
        $loadRelationships = $this->loadStateFromRelationshipsUsing;
        $this->loadStateFromRelationshipsUsing(static function (FlexSpatieMediaLibraryFileUpload $component) use ($loadRelationships): void {
            if ($component->flexPendingReplacementCleanup !== null) {
                // Old rows remain for rollback. Native single-file hydration would pick the
                // old first row and overwrite the just-uploaded selection before commit.
                return;
            }
            if ($loadRelationships !== null) {
                $component->evaluate($loadRelationships);
            }
        });
        $this->afterStateHydrated(static function (BaseFileUpload $component): void {
            /** @var FlexSpatieMediaLibraryFileUpload $component */
            $component->snapshotBaselineFilePaths();
        });

        $this->saveUploadedFileUsing(function (
            BaseFileUpload $component,
            TemporaryUploadedFile $file,
            ?Model $record = null,
        ): string {
            /** @var FlexSpatieMediaLibraryFileUpload $component */
            app(UploadAdmission::class)->validate($component, $file);

            return app(SpatieUploadBridge::class)->save($component, $file, $record);
        });

        $this->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string|array|null $storedFileNames = null): ?array {
            /** @var FlexSpatieMediaLibraryFileUpload $component */
            return app(SpatieUploadBridge::class)->payload($component, $file);
        });
        $this->deleteUploadedFileUsing(function (BaseFileUpload $component, string $file): void {
            /** @var FlexSpatieMediaLibraryFileUpload $component */
            if ($component->shouldDeleteFileOnRemove()) {
                app(SpatieUploadBridge::class)->delete($component, $file);
            }
        });
        $this->saveRelationshipsUsing(function (BaseFileUpload $component): void {
            /** @var FlexSpatieMediaLibraryFileUpload $component */
            // Verify new media before removing existing members of the owned collection.
            $component->saveUploadedFiles();
            $component->prepareSpatieReplacementCleanup();
            app(UploadRecordLifecycle::class)->track($component);
            $record = $component->getRecord();
            if ($record instanceof Model && $record->exists) {
                app(UploadRecordLifecycle::class)->acceptIfReferenced($component, $record);
            }
        });

    }

    public function prepareSpatieReplacementCleanup(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof HasMedia) {
            return;
        }
        $media = ($record->fresh() ?? $record)->getMedia($this->getCollection() ?? 'default');
        if ($this->hasMediaFilter()) {
            $media = $this->filterMedia($media);
        }
        $keep = array_values(array_filter($this->getRawState() ?? [], is_string(...)));
        $removed = $media->reject(static fn ($item): bool => in_array((string) $item->uuid, $keep, true))->pluck('uuid')->all();
        $snapshot = clone $this;
        $snapshot->model(clone $record);
        $this->flexPendingReplacementCleanup = static function () use ($snapshot, $removed): void {
            foreach ($removed as $uuid) {
                app(SpatieUploadBridge::class)->delete($snapshot, (string) $uuid);
            }
        };
    }
}
