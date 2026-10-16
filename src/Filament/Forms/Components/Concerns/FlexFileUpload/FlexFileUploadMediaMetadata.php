<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifest;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Closure;
use Filament\Forms\Components\BaseFileUpload;

/** @mixin BaseFileUpload */
trait FlexFileUploadMediaMetadata
{
    public function writeMetadataFromMediaReceipt(MediaReceipt $receipt, ?UploadManifest $manifest = null): void
    {
        $statePath = $this->getStoreMetadataInPath();
        if ($statePath === null) {
            return;
        }
        $describe = static fn (MediaReceipt $item): array => ['original_name' => $item->file->originalName,
            'mime' => $item->file->mimeType, 'size_kb' => round($item->file->bytes / 1024, 2),
            'width' => $item->file->width, 'height' => $item->file->height, 'sha256' => $item->file->sha256];
        $metadata = $describe($receipt);
        if ($manifest !== null) {
            $metadata['original_name'] = $manifest->original->file->originalName;
            $metadata['source'] = $describe($manifest->original);
            $metadata['conversions'] = array_map($describe, $manifest->variants);
        }
        $get = $this->makeGetUtility();
        $set = $this->makeSetUtility();
        if ($this->isMultiple()) {
            $existing = $get($statePath) ?? [];
            $existing[$receipt->identifier] = $metadata;
            $set($statePath, $existing);
        } else {
            $set($statePath, $metadata);
        }
    }

    /** Restore sidecar state if persistence or publication is rejected. */
    public function snapshotUploadMetadata(): Closure
    {
        $path = $this->getStoreMetadataInPath();
        $get = $this->makeGetUtility();
        $set = $this->makeSetUtility();
        $previous = $path === null ? null : $get($path);

        return static function () use ($path, $set, $previous): void {
            if ($path !== null) {
                $set($path, $previous);
            }
        };
    }

    public function removeUploadMetadata(string $identifier): void
    {
        $statePath = $this->getStoreMetadataInPath();
        if ($statePath === null) {
            return;
        }
        $set = $this->makeSetUtility();
        if (! $this->isMultiple()) {
            $set($statePath, null);

            return;
        }
        $get = $this->makeGetUtility();
        $metadata = $get($statePath) ?? [];
        unset($metadata[$identifier]);
        $set($statePath, $metadata);
    }
}
