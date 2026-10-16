<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\DiskUploadBridge;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** @mixin BaseFileUpload */
trait FlexFileUploadFileClick
{
    protected bool|Closure $flexFileClickPreview = false;

    protected ?Closure $flexUploadedFileClickAction = null;

    protected ?Closure $flexUploadedFileClickUrl = null;

    /** Callback receives the server-owned file, fileKey and component. */
    public function fileClickAction(?Closure $callback): static
    {
        $this->flexFileClickPreview = false;
        $this->flexUploadedFileClickAction = $callback;
        $this->flexUploadedFileClickUrl = null;

        return $this;
    }

    /** Resolve an application URL on click, never from client-supplied file metadata. */
    public function fileClickUrl(?Closure $callback): static
    {
        $this->flexFileClickPreview = false;
        $this->flexUploadedFileClickUrl = $callback;
        $this->flexUploadedFileClickAction = null;

        return $this;
    }

    public function fileClickPreview(bool|Closure $condition = true): static
    {
        $this->flexUploadedFileClickAction = null;
        $this->flexUploadedFileClickUrl = null;
        $this->flexFileClickPreview = $condition;
        $this->fileViewer($condition);

        return $this;
    }

    public function shouldPreviewFileOnClick(): bool
    {
        return $this->hasFileViewer() && (bool) $this->evaluate($this->flexFileClickPreview);
    }

    public function hasUploadedFileClick(): bool
    {
        return $this->flexUploadedFileClickAction !== null || $this->flexUploadedFileClickUrl !== null;
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function activateUploadedFile(mixed $fileKey): ?string
    {
        if ($this->isDisabled() || ! $this->hasUploadedFileClick() || ! is_string($fileKey) || strlen($fileKey) > 256) {
            throw new MediaFailure('denied', 'File activation is unavailable.');
        }
        $state = $this->getRawState();
        $file = is_array($state) ? ($state[$fileKey] ?? null) : null;
        if (! $file instanceof TemporaryUploadedFile && ! is_string($file)) {
            throw new MediaFailure('file_missing', 'The attachment no longer belongs to this field.');
        }
        if ($file instanceof TemporaryUploadedFile) {
            if (! $file->exists()) {
                throw new MediaFailure('file_missing', 'The temporary attachment has expired.');
            }
            $name = $file->getClientOriginalName();
            $type = $file->getMimeType();
        } else {
            $payload = match (true) {
                $this instanceof FlexSpatieMediaLibraryFileUpload => app(SpatieUploadBridge::class)->readInteraction($this, $file),
                $this instanceof FlexFileUpload => app(DiskUploadBridge::class)->readInteraction($this, $file, $this->getStoredFileNames()),
                default => null,
            };
            if ($payload === null) {
                throw new MediaFailure('denied', 'File ownership could not be proven.');
            }
            $name = $payload['name'];
            $type = $payload['type'];
        }
        $callback = $this->flexUploadedFileClickUrl ?? $this->flexUploadedFileClickAction;
        $result = $this->evaluate($callback, ['file' => $file, 'fileKey' => $fileKey, 'fileName' => $name,
            'mimeType' => $type, 'component' => $this, 'livewire' => $this->getLivewire(), 'record' => $this->getRecord()]);
        if ($result instanceof RedirectResponse) {
            return $this->validateFileClickUrl($result->getTargetUrl());
        }
        if ($this->flexUploadedFileClickUrl !== null) {
            return $result === null ? null : $this->validateFileClickUrl($result);
        }

        return null;
    }

    private function validateFileClickUrl(mixed $url): string
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)
            || str_contains($url, '\\') || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || (! (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
                && (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
                    || ! is_string(parse_url($url, PHP_URL_HOST))))) {
            throw new MediaFailure('invalid_url', 'Uploaded file URLs must be valid application-relative or HTTP(S) URLs.');
        }

        return $url;
    }
}
