<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\DiskUploadBridge;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** @mixin BaseFileUpload */
trait FlexFileUploadImageEditor
{
    private function imageEditorFile(string $fileKey): TemporaryUploadedFile|string
    {
        if ($this->isDisabled() || ! $this->hasImageEditor()) {
            throw new MediaFailure('denied', 'Image editing is disabled.');
        }
        $file = ($this->getRawState() ?? [])[$fileKey] ?? null;
        if (! $file instanceof TemporaryUploadedFile && ! is_string($file)) {
            throw new MediaFailure('file_missing', 'The image no longer belongs to this field.');
        }
        if (is_string($file)) {
            // The bridge proves record/collection ownership and URL policy.
            $this->getImageEditorSource($fileKey);
        } elseif (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            throw new MediaFailure('image_type_unsupported', 'The editor requires an image.');
        }

        return $file;
    }

    /** Resolve fresh signed ORIGINAL bytes only when the editor is opened.
     * @return array{name: string, type: string, url: string}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getImageEditorSource(string $fileKey): array
    {
        if ($this->isDisabled() || ! $this->hasImageEditor()) {
            throw new MediaFailure('denied', 'Image editing is disabled.');
        }
        $file = ($this->getRawState() ?? [])[$fileKey] ?? null;
        if (! is_string($file)) {
            throw new MediaFailure('file_missing', 'The stored image no longer belongs to this field.');
        }
        $snapshot = clone $this;
        $snapshot->openable();
        if ($snapshot instanceof FlexSpatieMediaLibraryFileUpload) {
            $payload = app(SpatieUploadBridge::class)->payload($snapshot, $file);
        } elseif ($snapshot instanceof FlexFileUpload) {
            $payload = app(DiskUploadBridge::class)->payload($snapshot, $file, $this->getStoredFileNames());
        } else {
            throw new MediaFailure('adapter_state_incompatible', 'This field has no image source adapter.');
        }
        if (! is_array($payload) || ! str_starts_with($payload['type'], 'image/') || ! is_string($payload['openableUrl'])) {
            throw new MediaFailure('image_source_unavailable', 'The original image is unavailable.');
        }

        return ['name' => $payload['name'], 'type' => $payload['type'], 'url' => $payload['openableUrl']];
    }

    /** Capture owned draft values without removing the selected original.
     * @return array{fileKey: string, isStored: bool, editorial: array<string, mixed>|null}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function captureImageEditorFile(string $fileKey): array
    {
        $file = $this->imageEditorFile($fileKey);

        return ['fileKey' => $fileKey, 'isStored' => is_string($file), 'editorial' => $this->hasFileMetadataEditor() ? $this->readFileEditorialMetadata($fileKey) : null];
    }

    /** Replace the draft association only after the new image has uploaded.
     * @param  array<string, mixed>|null  $metadata
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function completeImageEditorReplacement(string $fileKey, string $replacedKey, ?array $metadata = null): void
    {
        $new = $this->imageEditorFile($fileKey);
        if (! $new instanceof TemporaryUploadedFile || $fileKey === $replacedKey) {
            throw new MediaFailure('denied', 'An editor replacement requires a new owned temporary image.');
        }
        $files = $this->getRawState() ?? [];
        if (! array_key_exists($replacedKey, $files)) {
            return; // An uncertain response can safely repeat an already completed swap.
        }
        $old = $this->imageEditorFile($replacedKey);
        if ($metadata !== null) {
            $this->inheritImageEditorMetadata($fileKey, $metadata);
        }
        $files = $this->getRawState() ?? [];
        $ordered = [];
        foreach ($files as $key => $file) {
            if ($key === $replacedKey) {
                $ordered[$fileKey] = $new;
            } elseif ($key !== $fileKey) {
                $ordered[$key] = $file;
            }
        }
        if (is_string($old)) {
            $this->removeStoredFileName($old);
        }
        // Original bytes remain owned by after-save acceptance/rollback or
        // Livewire temporary retention. Only the draft association changes.
        $this->rawState($ordered);
        $this->callAfterStateUpdated();
    }

    /** Reconcile a lost completion response without deleting an accepted replacement.
     * Returns true when the swap already completed; otherwise removes only the
     * unaccepted temporary association. Temporary bytes follow Livewire retention.
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function reconcileImageEditorReplacement(string $fileKey, string $replacedKey): bool
    {
        if ($this->isDisabled() || ! $this->hasImageEditor() || $fileKey === $replacedKey) {
            throw new MediaFailure('denied', 'Image replacement reconciliation is disabled.');
        }
        $files = $this->getRawState() ?? [];
        if (! array_key_exists($fileKey, $files)) {
            return false;
        }
        $new = $this->imageEditorFile($fileKey);
        if (! array_key_exists($replacedKey, $files)) {
            return true;
        }
        $this->imageEditorFile($replacedKey);
        if (! $new instanceof TemporaryUploadedFile) {
            throw new MediaFailure('denied', 'Only an unaccepted temporary replacement can be discarded.');
        }
        unset($files[$fileKey]);
        $this->rawState($files);
        if ($this->hasFileMetadataEditor()) {
            $path = $this->getFileMetadataStatePath();
            $all = ($this->getGetCallback())($path) ?? [];
            if (is_array($all)) {
                unset($all[$fileKey]);
                ($this->getSetCallback())($path, $all);
            }
        }
        $this->callAfterStateUpdated();

        return false;
    }

    /** Carry configured editorial values into a newly uploaded, owned image.
     * Missing required values remain draft values; final form validation applies.
     *
     * @param  array<string, mixed>  $metadata
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function inheritImageEditorMetadata(string $fileKey, array $metadata): void
    {
        $file = $this->imageEditorFile($fileKey);
        if (! $this->hasFileMetadataEditor() || ! $file instanceof TemporaryUploadedFile) {
            throw new MediaFailure('denied', 'Editorial inheritance requires an owned temporary image.');
        }
        $metadata = $this->validateFileEditorialMetadata($metadata, false);
        $path = $this->getFileMetadataStatePath();
        $all = ($this->getGetCallback())($path) ?? [];
        $all = is_array($all) ? $all : [];
        $all[$fileKey] = $metadata;
        ($this->getSetCallback())($path, $all);
        app(UploadRecordLifecycle::class)->track($this);
    }
}
