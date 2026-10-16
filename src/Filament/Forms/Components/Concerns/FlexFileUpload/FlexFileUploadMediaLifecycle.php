<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Support\FilamentApi;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\UploadAggregate;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Support\Arr;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/** @mixin BaseFileUpload */
trait FlexFileUploadMediaLifecycle
{
    /** Preserve native temporary bytes for a lost-response retry; Livewire's bounded TTL owns their cleanup. */
    public function saveUploadedFiles(): void
    {
        if ($this->hasFileMetadataEditor()) {
            $this->getFileEditorialState();
        }
        if (! $this->usesMediaStaging() || $this->saveUploadedFileUsing === null) {
            parent::saveUploadedFiles();

            return;
        }
        if (blank($this->getRawState())) {
            $this->rawState([]);

            return;
        }
        if (! $this->shouldStoreFiles()) {
            return;
        }
        $rawState = array_filter(array_map(function (TemporaryUploadedFile|string $file): TemporaryUploadedFile|string|null {
            if (! $file instanceof TemporaryUploadedFile) {
                return $file;
            }
            $stored = $this->evaluate($this->saveUploadedFileUsing, ['file' => $file]);
            if (is_string($stored)) {
                $this->storeFileName($stored, $file->getClientOriginalName());
            }

            return $stored;
        }, Arr::wrap($this->getRawState())));
        if ($this->isReorderable && $this->reorderUploadedFilesUsing !== null) {
            $rawState = $this->evaluate($this->reorderUploadedFilesUsing, ['rawState' => $rawState, 'state' => $rawState]);
        }
        $this->rawState($rawState);
        $this->callAfterStateUpdated();
    }

    /** @return array<mixed> */
    public function getValidationRules(): array
    {
        $rules = parent::getValidationRules();
        // Older Filament only drops forged paths while saving. The staging path
        // needs the same rejection before validation returns a persistable value.
        if (FilamentApi::hasMethod(BaseFileUpload::class, 'dropTamperedFilePaths') && $this->shouldPreventFilePathTampering()) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail): void {
                $originalPaths = $this->getOriginalFilePaths();
                foreach (Arr::wrap($value) as $file) {
                    if (is_string($file) && ! $this->isFilePathAuthorized($file, $originalPaths)) {
                        $fail(__($this->getValidationMessages()['tampered'] ?? 'validation.in', ['attribute' => $this->getValidationAttribute()]));

                        return;
                    }
                }
            };
        }
        $limit = $this->getMaxTotalSizeKb();
        if ($limit !== null) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($limit): void {
                try {
                    $bytes = app(UploadAggregate::class)->bytes($this, Arr::wrap($value));
                } catch (Throwable) {
                    $fail(__('filament-flex-fields::default.file_upload.validation.storage_unavailable'));

                    return;
                }
                if ($bytes > $limit * 1024) {
                    $fail(__('filament-flex-fields::default.file_upload.validation.max_total_size', ['max' => number_format($limit, 0)]));
                }
            };
        }

        return $rules;
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function deleteUploadedFile(string $fileKey): static
    {
        if ($this->isDisabled() || ! $this->isDeletable()) {
            return $this;
        }
        $file = ($this->getRawState() ?? [])[$fileKey] ?? null;
        if (is_string($file)) {
            if ($this->shouldPreventFilePathTampering() && ! $this->isFilePathAuthorized($file)) {
                return $this;
            }
            // Storage failure must leave authoritative field state available for retry.
            if ($this->deleteUploadedFileUsing !== null) {
                $this->evaluate($this->deleteUploadedFileUsing, ['file' => $file]);
            }
        }
        $removed = $this->removeUploadedFile($fileKey);
        if (is_string($removed)) {
            $this->removeUploadMetadata($removed);
        }

        return $this;
    }
}
