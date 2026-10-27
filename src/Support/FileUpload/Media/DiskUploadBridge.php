<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\Events\MediaOperationObserved;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadNotifications;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Images\UploadConversions;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadStaging;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifest;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifestRepository;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaReceiptResolver;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaDescriptor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageBundle;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\QueuedImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/** Native path state, one persistence engine and explicit receipts across disks. */
final class DiskUploadBridge
{
    public function __construct(
        private readonly MediaOs $media,
        private readonly ImageMedia $images,
        private readonly UploadManifestRepository $manifests,
        private readonly UploadUrls $urls,
        private readonly UploadConversions $conversions,
        private readonly UploadAdmission $admission,
        private readonly MediaRegistry $registry,
        private readonly UploadStaging $staging,
        private readonly QueuedImageConversions $queuedImages,
    ) {}

    public function save(FlexFileUpload $field, TemporaryUploadedFile $file): string
    {
        if ($field->hasFileMetadataEditor()) {
            $field->getFileEditorialState();
        }
        $intent = $field->makeUploadMediaWrite($file)->identified((string) Str::uuid());
        $restoreMetadata = $field->snapshotUploadMetadata();
        $manifest = null;
        $journalled = false;
        $promoted = false;
        try {
            $this->admission->validate($field, $file);
            $plan = $this->conversions->forFile($field, (string) $file->getMimeType());
            $finalize = function (UploadManifest $result, MediaWrite $write) use ($field, &$manifest, &$journalled): UploadManifest {
                $manifest = $result = $result->withContext($write);
                $primary = $result->primaryReceipt();
                // The inherited disk field's native hydration requires disk-compatible path state.
                if ($primary->adapter === 'spatie' || $primary->disk !== $field->getDiskName() || $primary->identifier !== $primary->path) {
                    throw new MediaFailure('adapter_state_incompatible', 'Use the Spatie wrapper or a disk-compatible upload adapter.');
                }
                $field->finalizeUploadMedia($primary, $write);
                $field->writeMetadataFromMediaReceipt($primary, $result);
                $journalled = true;
                $this->manifests->write($result);

                return $result;
            };
            $manifest = $this->staging->store($field, $file, $intent,
                function (MediaSource $source, MediaWrite $write, \Closure $journal) use ($field, $plan, $finalize): MediaReceipt {
                    if ($plan['conversions'] !== [] && $field->shouldQueueImageConversions()) {
                        return $this->media->store($source, $write, function (MediaReceipt $receipt, MediaWrite $write) use ($field, $plan, $finalize, $journal): void {
                            $id = $this->queuedImages->prepare($receipt, $write, $plan['conversions'], $field->getImageConversionQueue(), $field->getImageLimits());
                            try {
                                $journal($finalize(new UploadManifest($receipt, context: ['queued_images' => $id, 'queued_preview' => $plan['primary'] === 'original' ? null : $plan['primary']]), $write));
                            } catch (Throwable $error) {
                                $this->queuedImages->cancel($id, $write);
                                throw $error;
                            }
                        });
                    }
                    if ($plan['conversions'] === []) {
                        return $this->media->store($source, $write,
                            static fn (MediaReceipt $receipt) => $journal($finalize(new UploadManifest($receipt), $write)));
                    }
                    $bundle = $this->images->store($source, $write, $plan['conversions'], $field->getImageLimits(),
                        static fn (ImageBundle $bundle) => $journal($finalize(new UploadManifest($bundle->original, $bundle->variants, $plan['primary']), $write)));

                    return $bundle->original;
                },
                function (UploadManifest $result) use ($field): void {
                    $durable = $this->manifests->read($result->primaryReceipt()->disk, $result->primaryReceipt()->identifier);
                    if ($durable === null || $durable->encode() !== $result->encode()) {
                        throw new MediaFailure('stage_ambiguous', 'Recovery requires the complete durable upload manifest.');
                    }
                    foreach ($result->receipts() as $receipt) {
                        $write = $this->receiptIntent($field, $receipt, $result);
                        $receipt = $this->restore($receipt, $write);
                        $this->media->verify($receipt, $write);
                    }
                    foreach ($result->variants as $receipt) {
                        $write = $this->receiptIntent($field, $receipt, $result);
                        $this->registry->adapter($receipt->adapter)->commit($this->restore($receipt, $write), $write);
                    }
                },
                function (UploadManifest $result) use ($field): bool {
                    $clean = true;
                    if (is_string($result->context['queued_images'] ?? null)) {
                        $this->queuedImages->cancel($result->context['queued_images'], $this->receiptIntent($field, $result->original, $result));
                    }
                    foreach (array_reverse($result->receipts()) as $receipt) {
                        $clean = $this->media->compensate($receipt, $this->receiptIntent($field, $receipt, $result)) && $clean;
                    }
                    if ($clean) {
                        $primary = $result->primaryReceipt();
                        $this->manifests->delete($primary->disk, $primary->identifier);
                    }

                    return $clean;
                });
            $promoted = true;
            $field->writeMetadataFromMediaReceipt($manifest->primaryReceipt(), $manifest);
            $field->promoteFileEditorialMetadata($file, $manifest->primaryReceipt()->identifier);
        } catch (Throwable $error) {
            $restoreMetadata();
            if ($promoted) {
                $error = $error instanceof MediaFailure ? $error : new MediaFailure('upload_finalization_failed', 'Committed upload requires native state reconciliation.', $error);
                $error->cleanupFailed = true;
            }
            if ($journalled && $manifest !== null && (! $error instanceof MediaFailure || (! $error->cleanupFailed && $error->reason !== 'manifest_exists'))) {
                try {
                    $primary = $manifest->primaryReceipt();
                    $this->manifests->delete($primary->disk, $primary->identifier);
                } catch (Throwable $cleanup) {
                    UploadNotifications::report($cleanup);
                    if ($error instanceof MediaFailure) {
                        $error->cleanupFailed = true;
                    }
                }
            }
            if ($manifest !== null && ! $promoted && is_string($manifest->context['queued_images'] ?? null)) {
                try {
                    $this->queuedImages->cancel($manifest->context['queued_images'], $intent);
                } catch (Throwable $cleanup) {
                    UploadNotifications::report($cleanup);
                }
            }
            $field->notifyUploadEvent(new UploadEvent(UploadEventType::Rejected, $intent,
                reason: $error instanceof MediaFailure ? $error->reason : 'validation_or_storage_failed',
                cleanupFailed: $error instanceof MediaFailure && $error->cleanupFailed));
            throw $error;
        }
        if (is_string($manifest->context['queued_images'] ?? null)) {
            app(UploadRecordLifecycle::class)->track($field);
        }
        $primary = $manifest->primaryReceipt();
        $field->notifyStoredUpload($primary, $intent->identified($manifest->original->operationId), $file);

        return $primary->identifier;
    }

    /** Called only after the lifecycle proves the fresh record references this path. */
    public function dispatchConversions(FlexFileUpload $field, string $path, Model $record): void
    {
        $this->authorizePath($field, $path);
        $manifest = $this->manifests->read($field->getDiskName(), $path);
        if ($manifest !== null && is_string($manifest->context['queued_images'] ?? null)) {
            $this->queuedImages->snapshot($manifest->context['queued_images'], $manifest->original, $this->receiptIntent($field, $manifest->original, $manifest));
            $this->queuedImages->dispatch($manifest->context['queued_images'], $record);
        }
    }

    public function authorizeMetadata(FlexFileUpload $field, string $path): void
    {
        $this->authorizePath($field, $path);
        $manifest = $this->manifests->read($field->getDiskName(), $path);
        $receipt = $manifest?->primaryReceipt() ?? $this->legacy($field, $path);
        $intent = $this->receiptIntent($field, $receipt, $manifest);
        $this->media->authorize('metadata', $intent, $receipt);
    }

    /** Authorize an interaction without generating preview/download URLs or running conversions.
     * @param  array<string, string>|string|null  $names
     * @return array{name: string, type: string}
     */
    public function readInteraction(FlexFileUpload $field, string $path, string|array|null $names): array
    {
        if ($path === '' || strlen($path) > 4096 || str_starts_with($path, '/') || str_contains($path, '\\')
            || preg_match('/[\x00-\x1f\x7f]/', $path) || array_intersect(explode('/', $path), ['', '.', '..']) !== []
            || ! $field->isFilePathAuthorized($path)) {
            throw new MediaFailure('denied', 'File activation requires an original record path or an explicit path authorization policy.');
        }
        $manifest = $this->manifests->read($field->getDiskName(), $path);
        $receipt = $manifest->original ?? $this->legacy($field, $path);
        $this->media->authorize('url', $this->receiptIntent($field, $receipt, $manifest), $receipt);
        $name = $field->isMultiple() && is_array($names) ? ($names[$path] ?? null) : (is_string($names) ? $names : null);

        return ['name' => $name ?? $receipt->file->originalName, 'type' => $receipt->file->mimeType];
    }

    /**
     * @param  array<string, string>|string|null  $names
     * @return array{name: string, size: int, type: string, url: ?string, openableUrl: ?string, downloadableUrl: ?string}
     */
    public function payload(FlexFileUpload $field, string $path, string|array|null $names): array
    {
        $this->authorizePath($field, $path);
        $manifest = $this->manifests->read($field->getDiskName(), $path);
        $primary = $manifest?->primaryReceipt() ?? $this->legacy($field, $path);
        $preview = $field->getPreviewConversion() ?? ($manifest?->context['queued_preview'] ?? null);
        $previewReceipt = $preview !== null && $manifest !== null ? ($manifest->variants[$preview] ?? $primary) : $primary;
        $original = $manifest === null ? $primary : $manifest->original;
        $intent = $this->receiptIntent($field, $original, $manifest);
        $processing = null;
        if ($manifest !== null && is_string($manifest->context['queued_images'] ?? null)) {
            $processing = $this->queuedImages->snapshot($manifest->context['queued_images'], $original, $intent);
            if ($processing['status'] === 'ready' && $preview !== null && isset($processing['variants'][$preview])) {
                $previewReceipt = $processing['variants'][$preview];
                $manifest = new UploadManifest($original, $processing['variants'], context: $manifest->context);
            }
        }
        $previewIntent = $this->receiptIntent($field, $previewReceipt, $manifest);
        $previewUrl = $this->urls->resolve($this->restore($previewReceipt, $previewIntent), $previewIntent);
        $originalUrl = null;
        if ($field->isOpenable() || $field->isDownloadable()) {
            $originalUrl = $original->operationId === $previewReceipt->operationId ? $previewUrl : $this->urls->resolve($this->restore($original, $intent), $intent);
        }
        $name = $field->isMultiple() && is_array($names) ? ($names[$path] ?? null) : (is_string($names) ? $names : null);

        return ['name' => $name ?? $original->file->originalName, 'size' => $primary->file->bytes,
            'correlationId' => MediaOperationObserved::identity($original->operationId),
            'type' => $primary->file->mimeType,
            'processingStatus' => $processing['status'] ?? null,
            'processingError' => $processing['error'] ?? null,
            'url' => $field->resolveUploadPreviewUrl($path, $previewUrl),
            'openableUrl' => $field->isOpenable() ? $originalUrl : null,
            'downloadableUrl' => $field->isDownloadable() ? $originalUrl : null];
    }

    /** Null retains the authorized original when a conversion is missing or still queued. */
    public function conversionUrl(FlexFileUpload $field, string $path, string $name): ?string
    {
        $this->authorizePath($field, $path);
        $manifest = $this->manifests->read($field->getDiskName(), $path);
        if ($manifest === null) {
            return null;
        }
        $intent = $this->receiptIntent($field, $manifest->original, $manifest);
        $this->media->authorize('url', $intent, $manifest->original);
        $variant = $manifest->variants[$name] ?? null;
        if ($variant === null && is_string($manifest->context['queued_images'] ?? null)) {
            $state = $this->queuedImages->snapshot($manifest->context['queued_images'], $manifest->original, $intent);
            $variant = $state['status'] === 'ready' ? ($state['variants'][$name] ?? null) : null;
        }
        if ($variant === null) {
            return null;
        }
        $variantIntent = $this->receiptIntent($field, $variant, $manifest);

        return $this->urls->resolve($this->restore($variant, $variantIntent), $variantIntent);
    }

    /** Called only by the owned server recovery coordinator after core source verification. */
    public function restoreReconciledUpload(FlexFileUpload $field, UploadManifest $result, MediaWrite $intent): void
    {
        $primary = $result->primaryReceipt();
        if ($primary->adapter === 'spatie' || $primary->disk !== $field->getDiskName() || $primary->identifier !== $primary->path) {
            throw new MediaFailure('adapter_state_incompatible', 'Recovery must preserve native disk-compatible state.');
        }
        foreach ($result->receipts() as $receipt) {
            $write = $this->receiptIntent($field, $receipt, $result);
            $receipt = $this->restore($receipt, $write);
            $this->media->authorize('recover', $write, $receipt);
            $this->media->verify($receipt, $write);
        }
        $field->finalizeUploadMedia($primary, $intent);
        $durable = $this->manifests->read($primary->disk, $primary->identifier);
        if ($durable === null) {
            $this->manifests->write($result);
        } elseif ($durable->encode() !== $result->encode()) {
            throw new MediaFailure('stage_corrupt', 'Existing receipt set conflicts with reconciled storage.');
        }
        foreach ($result->variants as $receipt) {
            $write = $this->receiptIntent($field, $receipt, $result);
            $this->registry->adapter($receipt->adapter)->commit($this->restore($receipt, $write), $write);
        }
        $field->writeMetadataFromMediaReceipt($primary, $result);
    }

    public function delete(FlexFileUpload $field, string $path): int
    {
        $this->authorizePath($field, $path);
        $disk = $field->getDiskName();

        return Cache::lock('fff:upload:delete:'.hash('sha256', $disk."\0".$path), 300)->block(10, function () use ($field, $disk, $path): int {
            $manifest = $this->manifests->read($disk, $path);
            $intent = $field->makeUploadMediaWrite();
            $primary = $manifest?->primaryReceipt();
            if ($primary === null) {
                if (! $field->getDisk()->exists($path)) {
                    return 0;
                }
                $primary = $this->legacy($field, $path);
            }
            $receipts = $manifest?->receipts() ?? ['original' => $primary];
            $restored = [];
            foreach ($receipts as $name => $receipt) {
                $receiptIntent = $this->receiptIntent($field, $receipt, $manifest);
                $restored[$name] = $this->restore($receipt, $receiptIntent);
                $this->media->authorize('delete', $receiptIntent, $restored[$name]);
            }
            $queuedFreed = 0;
            if ($manifest !== null && is_string($manifest->context['queued_images'] ?? null)) {
                $queuedFreed = $this->queuedImages->cancel($manifest->context['queued_images'], $this->receiptIntent($field, $manifest->original, $manifest));
            }
            // Keep the primary/native state readable until all other members are removed.
            uasort($restored, static fn (MediaReceipt $a, MediaReceipt $b): int => (int) ($a->identifier === $path && $a->disk === $disk) <=> (int) ($b->identifier === $path && $b->disk === $disk));
            $freed = $queuedFreed;
            foreach ($restored as $receipt) {
                if ($receipt->adapter === 'disk' && ! Storage::disk($receipt->disk)->exists($receipt->path)) {
                    continue;
                }
                $this->media->delete($receipt, $this->receiptIntent($field, $receipt, $manifest));
                $freed += $receipt->file->bytes;
            }
            if ($manifest !== null) {
                $this->manifests->delete($disk, $path);
                $leaseKey = $manifest->context['metadata']['flex_upload_lease'] ?? null;
                if (is_string($leaseKey)) {
                    try {
                        $this->staging->deleted($field, $leaseKey);
                    } catch (Throwable $cleanup) {
                        UploadNotifications::report($cleanup);
                    }
                }
            }
            $field->removeUploadMetadata($path);
            $field->notifyDeletedUpload($primary, $intent, $freed);

            return $freed;
        });
    }

    private function authorizePath(FlexFileUpload $field, string $path): void
    {
        if ($field->shouldPreventFilePathTampering() && ! $field->isFilePathAuthorized($path)) {
            throw new MediaFailure('denied', 'Upload path is not authorized for this field.');
        }
    }

    private function receiptIntent(FlexFileUpload $field, MediaReceipt $receipt, ?UploadManifest $manifest): MediaWrite
    {
        $base = $field->makeUploadMediaWrite();
        $context = $manifest === null ? [] : $manifest->context;
        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $policy = $base->policy ?? $field->getMediaPolicy();
        if (($context['visibility'] ?? null) === 'private' && $policy->visibility !== 'private') {
            $policy = new MediaPolicy($policy->maxBytes, $policy->minBytes, $policy->mimeTypes,
                $policy->requireScanner, $policy->scanBeforeWrite, $policy->scanAfterWrite, $policy->verifyIntegrity, 'private', $policy->quarantineDisk);
        }
        $kind = is_string($context['kind'] ?? null) ? $context['kind'] : $base->kind;
        if ($manifest !== null) {
            foreach ($manifest->variants as $name => $variant) {
                if ($receipt->operationId === $variant->operationId) {
                    $kind = 'image-conversion';
                    $metadata = [...$metadata, 'conversion' => $name, 'original_operation' => $manifest->original->operationId];
                    $policy = new MediaPolicy($policy->maxBytes, 0, [$receipt->file->mimeType], $policy->requireScanner,
                        $policy->scanBeforeWrite, $policy->scanAfterWrite, $policy->verifyIntegrity, $policy->visibility, $policy->quarantineDisk);
                    break;
                }
            }

        }

        return new MediaWrite(adapter: $receipt->adapter, kind: $kind, disk: $receipt->disk,
            directory: dirname($receipt->path) === '.' ? '' : dirname($receipt->path),
            field: $base->field, record: $base->record, policy: $policy,
            metadata: $metadata, operationId: $receipt->operationId);
    }

    private function restore(MediaReceipt $receipt, MediaWrite $intent): MediaReceipt
    {
        $adapter = $this->registry->adapter($receipt->adapter);

        return $adapter instanceof MediaReceiptResolver ? $adapter->restore($receipt, $intent) : $receipt;
    }

    private function legacy(FlexFileUpload $field, string $path): MediaReceipt
    {
        $disk = $field->getDisk();

        return new MediaReceipt('disk', $path, $field->getDiskName(), $path,
            new MediaDescriptor((int) $disk->size($path), method_exists($disk, 'mimeType') ? (string) $disk->mimeType($path) : 'application/octet-stream', str_repeat('0', 64), basename($path)),
            'legacy-'.hash('sha256', $field->getDiskName()."\0".$path));
    }
}
