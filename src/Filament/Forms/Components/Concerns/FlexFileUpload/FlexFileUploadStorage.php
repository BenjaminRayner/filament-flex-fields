<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VoiceNoteRecorderField;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\UploadFailed;
use Bjanczak\FilamentFlexFields\Support\FileUpload\FileUploadMetadata;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\DiskUploadBridge;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureTenantDiskResolver;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaContext;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaKind;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaStorageDriver;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @mixin BaseFileUpload
 */
trait FlexFileUploadStorage
{
    protected bool|Closure $flexScopedDirectoryEnabled = false;

    protected string|Closure $flexScopedDirectoryPrefix = 'uploads';

    protected string|Closure|null $flexStoreMetadataIn = null;

    protected int|Closure|null $flexMaxTotalSizeKb = null;

    protected bool|Closure $flexDeleteFileOnRemove = false;

    protected bool|Closure $flexDeleteReplacedFiles = false;

    protected bool|Closure $flexPruneOrphanedOnSave = false;

    /**
     * @var (Closure(BaseFileUpload, string, int): void)|null
     */
    protected $flexDeletedStoredFileUsing = null;

    /**
     * @var (Closure(BaseFileUpload, string, TemporaryUploadedFile): void)|null
     */
    protected $flexAfterPersistUploadedFileUsing = null;

    /**
     * @var list<string>
     */
    protected array $flexBaselineFilePaths = [];

    protected ?Closure $flexPendingReplacementCleanup = null;

    public function scopedDirectory(string|Closure $prefix = 'uploads'): static
    {
        $this->flexScopedDirectoryEnabled = true;
        $this->flexScopedDirectoryPrefix = $prefix;

        return $this->directory($prefix);
    }

    public function directory(string|Closure|null $directory): static
    {
        if ($directory === null) {
            return parent::directory($directory);
        }

        return parent::directory(function (BaseFileUpload $component) use ($directory): string {
            /** @var static $component */
            $prefix = $component->evaluate($directory);
            $prefix = is_string($prefix) ? trim($prefix, '/') : 'uploads';

            if ($prefix === '') {
                $prefix = 'uploads';
            }

            if ($component->flexScopedDirectoryEnabled || MediaCaptureTenantDiskResolver::shouldApplyDirectoryPrefix()) {
                return MediaCaptureTenantDiskResolver::resolveDirectory(
                    $prefix,
                    $component->getRecord() instanceof Model ? $component->getRecord() : null,
                    Auth::id(),
                    ['field' => $component->getName()],
                );
            }

            return $prefix;
        });
    }

    public function createFormStrategy(bool|Closure $condition = true): static
    {
        $this->preventFilePathTampering($condition, function (BaseFileUpload $component, string $file): bool {
            $directory = trim((string) $component->getDirectory(), '/');

            if ($directory === '') {
                return false;
            }

            $normalizedFile = ltrim($file, '/');

            return str_starts_with($normalizedFile, $directory.'/') || $normalizedFile === $directory;
        });

        return $this;
    }

    public function storeMetadataIn(string|Closure $statePath): static
    {
        $this->flexStoreMetadataIn = $statePath;

        return $this;
    }

    public function maxTotalSizeKb(int|Closure $kilobytes): static
    {
        $this->flexMaxTotalSizeKb = $kilobytes;

        return $this;
    }

    public function deleteFileOnRemove(bool|Closure $condition = true): static
    {
        $this->flexDeleteFileOnRemove = $condition;

        return $this;
    }

    public function deleteReplacedFiles(bool|Closure $condition = true): static
    {
        $this->flexDeleteReplacedFiles = $condition;

        return $this;
    }

    /** Remove only abandoned members of this field's hydrated baseline, never scan a shared directory. */
    public function pruneOrphanedOnSave(bool|Closure $condition = true): static
    {
        $this->flexPruneOrphanedOnSave = $condition;

        return $this;
    }

    /**
     * Invoked after a stored path (and its thumb variant, if any) is deleted from disk.
     * The third argument is the number of bytes freed.
     *
     * @param  (Closure(BaseFileUpload, string, int): void)|null  $callback
     */
    public function deletedStoredFileUsing(?Closure $callback): static
    {
        $this->flexDeletedStoredFileUsing = $callback;

        return $this;
    }

    /**
     * Invoked after a file is persisted to disk and passes virus scan.
     *
     * @param  (Closure(BaseFileUpload, string, TemporaryUploadedFile): void)|null  $callback
     */
    public function afterPersistUploadedFileUsing(?Closure $callback): static
    {
        $this->flexAfterPersistUploadedFileUsing = $callback;

        return $this;
    }

    public function persistUploadedFileWithFlexProcessing(TemporaryUploadedFile $file): ?string
    {
        if ($this instanceof FlexSpatieMediaLibraryFileUpload) {
            return app(SpatieUploadBridge::class)->save($this, $file);
        }

        return app(DiskUploadBridge::class)->save($this, $file);
    }

    protected function makeMediaIngressContext(): MediaContext
    {
        $kind = MediaKind::Upload;

        if (is_a($this, VoiceNoteRecorderField::class)) {
            $kind = MediaKind::VoiceNote;
        } elseif (is_a($this, FlexImageUpload::class)) {
            $kind = MediaKind::Image;
        }

        return (new MediaContext(
            kind: $kind,
            driver: MediaStorageDriver::Disk,
            field: $this->getName(),
            record: ($record = $this->getRecord()) instanceof Model ? $record : null,
            disk: $this->getDiskName(),
            directory: $this->getDirectory(),
        ))->withCorrelationId((string) Str::uuid());
    }

    /**
     * Run the MediaCaptureOs virus-scan hook. When scan fails, records upload.fail.
     */
    public function passesMediaCaptureVirusScan(string $storedPath): bool
    {
        if (MediaCaptureOs::passesVirusScan($storedPath)) {
            return true;
        }

        app(ActivityDispatcher::class)->dispatch(new UploadFailed(
            field: $this->getName(), adapter: 'disk', reason: 'virus_scan',
            stage: 'post_persist', path: $storedPath,
        ));

        return false;
    }

    /**
     * Resolve a display/download URL via Media Ingress signed-URL capsule when registered.
     */
    public function resolveMediaCaptureSignedUrl(string $path): ?string
    {
        return FlexMedia::signedUrl($this->getDiskName(), $path, [
            'visibility' => $this->getVisibility(),
            'field' => $this->getName(),
        ]);
    }

    public function applyMediaCaptureStorageDefaults(): void
    {
        if ((bool) config('filament-flex-fields.media_capture.tenant.auto_disk', false)) {
            $this->disk(function (BaseFileUpload $component): string {
                /** @var static $component */
                return MediaCaptureTenantDiskResolver::resolveDisk(null, [
                    'field' => $component->getName(),
                    'record' => $component->getRecord(),
                ]);
            });
        }
    }

    public function registerFlexFileUploadHooks(): void
    {
        if (! $this instanceof FlexFileUpload) {
            throw new \LogicException('Disk hooks require FlexFileUpload; Spatie has its own owned bridge.');
        }
        $this->applyMediaCaptureStorageDefaults();
        $this->afterStateHydrated(function (BaseFileUpload $component): void {
            /** @var static $component */
            $component->hydrateFiles();
            $component->snapshotBaselineFilePaths();
        });

        $this->beforeStateDehydrated(function (BaseFileUpload $component): void {
            /** @var static $component */
            $component->saveUploadedFiles();
            $component->prepareReplacementCleanup();
            app(UploadRecordLifecycle::class)->track($component);
        }, true);

        $this->saveRelationshipsUsing(static function (FlexFileUpload $component): void {
            $record = $component->getRecord();
            if ($record instanceof Model && $record->exists) {
                app(UploadRecordLifecycle::class)->acceptIfReferenced($component, $record);
            }
        });

        $this->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
            /** @var static $component */

            return $component->persistUploadedFileWithFlexProcessing($file);
        });

        $this->getUploadedFileUsing(function (FlexFileUpload $component, string $file, string|array|null $storedFileNames): array {
            return app(DiskUploadBridge::class)->payload($component, $file, $storedFileNames);
        });

        $this->deleteUploadedFileUsing(function (BaseFileUpload $component, string $file): void {
            /** @var static $component */
            if (! $component->shouldDeleteFileOnRemove()) {
                return;
            }

            $component->deleteStoredPathWithVariants($file);
        });
    }

    /**
     * Nested repeater/builder uploads share the parent Eloquent record, but their
     * field name is not a model attribute. Filament's default implementation calls
     * getAttribute() eagerly and throws MissingAttributeException when
     * Model::preventAccessingMissingAttributes() is enabled.
     *
     * @return list<string>
     */
    public function getOriginalFilePaths(): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return [];
        }

        $attribute = $this->getName();

        if (! $record->hasAttribute($attribute)) {
            return [];
        }

        return array_values(array_filter(
            Arr::wrap($record->getOriginal($attribute, $record->getAttribute($attribute))),
            static fn (mixed $path): bool => is_string($path) && filled($path),
        ));
    }

    public function snapshotBaselineFilePaths(): void
    {
        $this->flexBaselineFilePaths = $this->normalizeFilePaths($this->getRawState());
    }

    /**
     * @return list<string>
     */
    public function getBaselineFilePaths(): array
    {
        return $this->flexBaselineFilePaths;
    }

    /** Prepare owned removals without deleting anything before the record accepts new state. */
    public function prepareReplacementCleanup(): void
    {
        if (! $this->shouldDeleteReplacedFiles() && ! $this->shouldPruneOrphanedOnSave()) {
            return;
        }
        $removed = array_values(array_diff($this->flexBaselineFilePaths, $this->normalizeFilePaths($this->getRawState())));
        $snapshot = clone $this;
        if (($record = $this->getRecord()) instanceof Model) {
            $snapshot->model(clone $record);
        }
        $this->flexPendingReplacementCleanup = static function () use ($snapshot, $removed): void {
            foreach ($removed as $path) {
                $snapshot->deleteStoredPathWithVariants($path);
            }
        };
    }

    /** Invoke after the record commit (for example, from an after-save transaction callback). */
    public function cleanupReplacedFilesAfterSave(): void
    {
        if ($this->flexPendingReplacementCleanup === null) {
            return;
        }
        ($this->flexPendingReplacementCleanup)();
        $this->flexPendingReplacementCleanup = null;
        $this->snapshotBaselineFilePaths();
    }

    public function deleteReplacedFilesFromDisk(): void
    {
        if (! $this->shouldDeleteReplacedFiles()) {
            return;
        }

        $currentPaths = $this->normalizeFilePaths($this->getRawState());
        $removedPaths = array_diff($this->flexBaselineFilePaths, $currentPaths);

        foreach ($removedPaths as $path) {
            $this->deleteStoredPathWithVariants($path);
        }
    }

    public function pruneOrphanedFilesFromDirectory(): void
    {
        if (! $this->shouldPruneOrphanedOnSave()) {
            return;
        }

        // A directory prefix is not ownership. Only the hydrated baseline belongs to this field.
        foreach (array_diff($this->flexBaselineFilePaths, $this->normalizeFilePaths($this->getRawState())) as $path) {
            $this->deleteStoredPathWithVariants($path);
        }
    }

    /** Delete explicit Media OS receipts; never infer or scan siblings. */
    public function deleteStoredPathWithVariants(string $path): int
    {
        if ($this instanceof FlexSpatieMediaLibraryFileUpload) {
            return app(SpatieUploadBridge::class)->delete($this, $path);
        }

        return app(DiskUploadBridge::class)->delete($this, $path);
    }

    protected function thumbVariantPathFor(string $path): ?string
    {
        if ($path === '' || str_ends_with($path, '_thumb.webp') || ! str_contains($path, '.')) {
            return null;
        }

        return Str::of($path)->beforeLast('.')->append('_thumb.webp')->toString();
    }

    public function writeMetadataForStoredPath(string $storedPath, ?string $originalName = null, ?TemporaryUploadedFile $temporaryFile = null): void
    {
        $metadataPath = $this->getStoreMetadataInPath();

        if (blank($metadataPath)) {
            return;
        }

        $metadata = $temporaryFile instanceof TemporaryUploadedFile
            ? FileUploadMetadata::fromTemporaryFile($temporaryFile)
            : FileUploadMetadata::fromDiskPath($this->getDisk(), $storedPath, $originalName);

        $set = $this->makeSetUtility();
        $get = $this->makeGetUtility();

        if ($this->isMultiple()) {
            $existing = $get($metadataPath) ?? [];
            $existing[$storedPath] = $metadata;
            $set($metadataPath, $existing);

            return;
        }

        $set($metadataPath, $metadata);
    }

    /**
     * @return list<string>
     */
    protected function normalizeFilePaths(mixed $state): array
    {
        return array_values(array_filter(array_map(
            static function (mixed $file): ?string {
                if (is_string($file) && filled($file)) {
                    return $file;
                }

                return null;
            },
            Arr::wrap($state),
        )));
    }

    public function getStoreMetadataInPath(): ?string
    {
        $path = $this->evaluate($this->flexStoreMetadataIn);

        return filled($path) ? (string) $path : null;
    }

    public function getMaxTotalSizeKb(): ?int
    {
        $value = $this->evaluate($this->flexMaxTotalSizeKb);

        return is_numeric($value) ? (int) $value : null;
    }

    public function shouldDeleteFileOnRemove(): bool
    {
        return (bool) $this->evaluate($this->flexDeleteFileOnRemove);
    }

    public function shouldDeleteReplacedFiles(): bool
    {
        return (bool) $this->evaluate($this->flexDeleteReplacedFiles);
    }

    public function shouldPruneOrphanedOnSave(): bool
    {
        return (bool) $this->evaluate($this->flexPruneOrphanedOnSave);
    }
}
