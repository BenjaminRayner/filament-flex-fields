<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class UploadAggregate
{
    /** @param array<array-key, mixed> $files */
    public function bytes(FlexFileUpload|FlexSpatieMediaLibraryFileUpload $field, array $files): int
    {
        $total = 0;
        foreach ($files as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $bytes = $file->getSize();
            } elseif (is_string($file)) {
                if ($field instanceof FlexSpatieMediaLibraryFileUpload) {
                    $media = app(SpatieUploadBridge::class)->find($field, $file);
                    if ($media === null) {
                        throw new MediaFailure('denied', 'Stored upload is not owned by this field.');
                    }
                    $bytes = (int) $media->size;
                } else {
                    if ($field->shouldPreventFilePathTampering() && ! $field->isFilePathAuthorized($file)) {
                        throw new MediaFailure('denied', 'Stored upload is not owned by this field.');
                    }
                    app(MediaOs::class)->authorize('inspect', $field->makeUploadMediaWrite());
                    $bytes = $field->getDisk()->size($file);
                }
            } else {
                throw new MediaFailure('invalid_state', 'Invalid upload state.');
            }
            if ($bytes < 0 || $bytes > PHP_INT_MAX - $total) {
                throw new MediaFailure('size_rejected', 'Upload total exceeds its byte budget.');
            }
            $total += $bytes;
        }

        return $total;
    }
}
