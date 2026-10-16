<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Staging;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaReceiptResolver;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Closure;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class MediaStaging
{
    public function __construct(
        private readonly StageRepository $repository,
        private readonly MediaOs $media,
        private readonly MediaRegistry $registry,
    ) {}

    public function stage(MediaSource $source, MediaWrite $intent, string $owner): StageLease
    {
        $owner = $this->owner($owner);
        $this->media->authorize('stage', $intent);
        $id = bin2hex(random_bytes(16));
        $expires = time() + $this->repository->options->ttlSeconds;
        $prepared = $this->media->inspect($source, $intent);
        try {
            $this->repository->locked($id, function () use ($id, $expires, $owner, $prepared, $intent): void {
                $stream = $prepared->open();
                try {
                    if (! Storage::disk($this->repository->options->disk)->writeStream($this->repository->payloadPath($id), $stream, ['visibility' => 'private'])) {
                        throw new MediaFailure('stage_write_failed', 'Unable to stage media bytes.');
                    }
                    $this->repository->save($id, [
                        'owner' => $owner, 'state' => 'pending', 'expires' => $expires,
                        'target' => $this->target($intent), 'name' => $prepared->file->originalName,
                        'hash' => $prepared->file->sha256,
                        'record_type' => $intent->record?->getMorphClass(),
                        'record_key' => $intent->record?->exists ? (string) $intent->record->getKey() : null,
                    ]);
                } catch (Throwable $error) {
                    try {
                        $this->repository->removePayload($id);
                    } catch (Throwable $cleanup) {
                        $this->report($cleanup);
                    }
                    throw $error;
                } finally {
                    fclose($stream);
                }
            });
        } finally {
            $prepared->close();
        }

        return new StageLease($id, $expires);
    }

    /** A validation failure should retain this lease; commit only after the form is accepted. */
    public function commit(string $id, string $owner, MediaWrite $intent, ?Closure $persist = null, ?Closure $resume = null, bool $retainPayload = false): MediaReceipt
    {
        return $this->repository->locked($id, function () use ($id, $owner, $intent, $persist, $resume, $retainPayload): MediaReceipt {
            $data = $this->owned($id, $owner);
            if (! hash_equals($data['target'], $this->target($intent))) {
                throw new MediaFailure('stage_target_mismatch', 'Media lease belongs to another destination.');
            }
            if (($data['record_type'] !== null && $data['record_type'] !== $intent->record?->getMorphClass())
                || ($data['record_key'] !== null && $data['record_key'] !== (string) $intent->record?->getKey())) {
                throw new MediaFailure('stage_target_mismatch', 'Media lease belongs to another record.');
            }
            $intent = $intent->identified($id);
            $this->media->authorize('commit', $intent);
            if ($data['state'] === 'committed') {
                return $this->restore($data, $intent);
            }
            if ($data['state'] === 'promoting') {
                // A previous process may have saved bytes but died before the manifest commit.
                // Never silently create a second object from an ambiguous operation.
                $receipt = $this->restore($data, $intent);
                $this->media->verify($receipt, $intent);
                if ($resume !== null) {
                    $resume($receipt, $intent);
                }
                $this->registry->adapter($receipt->adapter)->commit($receipt, $intent);
            } elseif ($data['state'] === 'pending') {
                if ($data['expires'] <= time()) {
                    throw new MediaFailure('stage_expired', 'Media lease has expired.');
                }
                // Bind create-form leases to the newly saved record on their first promotion.
                $data['record_type'] = $intent->record?->getMorphClass();
                $data['record_key'] = $intent->record?->exists ? (string) $intent->record->getKey() : null;
                $data['state'] = 'promoting';
                $this->repository->save($id, $data);
                try {
                    $stream = Storage::disk($this->repository->options->disk)->readStream($this->repository->payloadPath($id));
                    if (! is_resource($stream)) {
                        throw new MediaFailure('stage_missing', 'Staged media bytes are unavailable.');
                    }
                } catch (Throwable $error) {
                    $data['state'] = 'pending';
                    try {
                        $this->repository->save($id, $data);
                    } catch (Throwable $journalFailure) {
                        $this->report($journalFailure);
                    }
                    throw $error instanceof MediaFailure ? $error : new MediaFailure('stage_missing', 'Staged media bytes are unavailable.', $error);
                }
                try {
                    $journal = function (MediaReceipt $receipt) use ($id, $intent, &$data): void {
                        $this->validateReceipt($data, $intent, $receipt);
                        if (! hash_equals($data['hash'], $receipt->file->sha256)) {
                            throw new MediaFailure('integrity_mismatch', 'Staged media bytes have changed.');
                        }
                        $data['receipt'] = ReceiptCodec::encode($receipt);
                        $this->repository->save($id, $data);
                    };
                    $source = MediaSource::stream($stream, $data['name']);
                    $receipt = $persist === null
                        ? $this->media->store($source, $intent->withoutProcessors(), $journal)
                        : $persist($source, $intent->withoutProcessors(), $journal);
                    if (! $receipt instanceof MediaReceipt || ! isset($data['receipt']) || ReceiptCodec::encode($receipt) !== $data['receipt']) {
                        $failure = new MediaFailure('stage_ambiguous', 'Promotion did not return its journalled original receipt.');
                        $failure->cleanupFailed = true;
                        throw $failure;
                    }
                } catch (Throwable $error) {
                    // Receipt-bearing uncertainty remains recoverable/visible, never implicitly retried.
                    if ($error instanceof MediaFailure && ! $error->cleanupFailed) {
                        unset($data['receipt']);
                        $data['state'] = 'pending';
                        try {
                            $this->repository->save($id, $data);
                        } catch (Throwable $journalFailure) {
                            $this->report($journalFailure);
                        }
                    }
                    throw $error;
                } finally {
                    fclose($stream);
                }
            } else {
                throw new MediaFailure('stage_abandoned', 'Media lease is no longer pending.');
            }
            $this->complete($id, $data, $retainPayload);

            return $receipt;
        });
    }

    /**
     * Supply an independently reconciled receipt after an interrupted adapter write.
     * This never guesses that an unjournalled write did not happen or retries it.
     */
    public function recover(string $id, string $owner, MediaWrite $intent, MediaReceipt $receipt, ?Closure $resume = null, bool $retainPayload = false): MediaReceipt
    {
        return $this->repository->locked($id, function () use ($id, $owner, $intent, $receipt, $resume, $retainPayload): MediaReceipt {
            $data = $this->owned($id, $owner);
            if ($data['state'] !== 'promoting' || ! hash_equals($data['target'], $this->target($intent))
                || ($data['record_type'] !== null && $data['record_type'] !== $intent->record?->getMorphClass())
                || ($data['record_key'] !== null && $data['record_key'] !== (string) $intent->record?->getKey())) {
                throw new MediaFailure('stage_target_mismatch', 'Recovery requires the original interrupted destination and record.');
            }
            $intent = $intent->identified($id);
            $this->media->authorize('recover', $intent, $receipt);
            $this->validateReceipt($data, $intent, $receipt);
            $data['receipt'] = ReceiptCodec::encode($receipt);
            $receipt = $this->restore($data, $intent);
            $this->media->verify($receipt, $intent);
            $this->repository->save($id, $data);
            if ($resume !== null) {
                $resume($receipt, $intent);
            }
            $this->registry->adapter($receipt->adapter)->commit($receipt, $intent);
            $this->complete($id, $data, $retainPayload);

            return $receipt;
        });
    }

    public function status(string $id, string $owner): string
    {
        return $this->owned($id, $owner)['state'];
    }

    /** Retained sources are released only after the application confirms its record commit. */
    public function confirm(string $id, string $owner): void
    {
        $this->repository->locked($id, function () use ($id, $owner): void {
            $data = $this->owned($id, $owner);
            if ($data['state'] !== 'committed') {
                throw new MediaFailure('stage_ambiguous', 'Only committed media may be confirmed.');
            }
            $this->repository->removePayload($id);
            $data['retained'] = false;
            $data['expires'] = time() + $this->repository->options->receiptTtlSeconds;
            $this->repository->save($id, $data);
        });
    }

    /** Read a retained source inside the lease lock; callers never receive its storage path. */
    public function withSource(string $id, string $owner, MediaWrite $intent, Closure $consume): void
    {
        $this->repository->locked($id, function () use ($id, $owner, $intent, $consume): void {
            $data = $this->owned($id, $owner);
            if (! hash_equals($data['target'], $this->target($intent))
                || ($data['record_type'] !== null && $data['record_type'] !== $intent->record?->getMorphClass())
                || ($data['record_key'] !== null && $data['record_key'] !== (string) $intent->record?->getKey())) {
                throw new MediaFailure('stage_target_mismatch', 'Media lease belongs to another destination.');
            }
            $this->media->authorize('stage-read', $intent->identified($id));
            $stream = Storage::disk($this->repository->options->disk)->readStream($this->repository->payloadPath($id));
            if (! is_resource($stream)) {
                throw new MediaFailure('stage_missing', 'Retained source is unavailable.');
            }
            try {
                $consume(MediaSource::stream($stream, $data['name']));
            } finally {
                fclose($stream);
            }
        });
    }

    /** Only an explicit failed DB transaction may return a promoted lease to pending. */
    public function rollback(string $id, string $owner, MediaWrite $intent, Closure $compensate, bool $unbindRecord = false): void
    {
        $this->repository->locked($id, function () use ($id, $owner, $intent, $compensate, $unbindRecord): void {
            $data = $this->owned($id, $owner);
            if (! hash_equals($data['target'], $this->target($intent))
                || ($data['record_type'] !== null && $data['record_type'] !== $intent->record?->getMorphClass())
                || ($data['record_key'] !== null && $data['record_key'] !== (string) $intent->record?->getKey())) {
                throw new MediaFailure('stage_target_mismatch', 'Rollback requires the original promoted destination.');
            }
            if ($data['state'] === 'pending') {
                return;
            }
            if (! in_array($data['state'], ['committed', 'promoting'], true)) {
                throw new MediaFailure('stage_abandoned', 'Rollback requires an active promotion.');
            }
            $this->media->authorize('rollback', $intent->identified($id));
            if (! Storage::disk($this->repository->options->disk)->exists($this->repository->payloadPath($id))) {
                throw new MediaFailure('stage_missing', 'Rollback requires a retained source.');
            }
            if ($compensate() !== true) {
                $failure = new MediaFailure('cleanup_failed', 'Promotion rollback requires reconciliation.');
                $failure->cleanupFailed = true;
                throw $failure;
            }
            unset($data['receipt']);
            $data['state'] = 'pending';
            $data['expires'] = time() + $this->repository->options->ttlSeconds;
            if ($unbindRecord) {
                $data['record_key'] = null;
            }
            $this->repository->save($id, $data);
        });
    }

    public function abandon(string $id, string $owner): void
    {
        $this->repository->locked($id, function () use ($id, $owner): void {
            $data = $this->owned($id, $owner);
            if ($data['state'] === 'committed') {
                return;
            }
            if ($data['state'] === 'promoting') {
                throw new MediaFailure('stage_ambiguous', 'An interrupted media promotion requires recovery.');
            }
            $data['state'] = 'abandoned';
            $this->repository->save($id, $data);
            $this->repository->removePayload($id);
        });
    }

    /** @return array{examined: int, removed: int, uncertain: int} */
    public function prune(bool $dryRun = false): array
    {
        $result = ['examined' => 0, 'removed' => 0, 'uncertain' => 0];
        foreach ($this->repository->candidates('leases') as $path) {
            if ($result['removed'] >= $this->repository->options->pruneBatchSize) {
                break;
            }
            $id = pathinfo($path, PATHINFO_FILENAME);
            if (! preg_match('/^[a-f0-9]{32}$/D', $id)) {
                continue;
            }
            $result['examined']++;
            try {
                $this->repository->locked($id, function () use ($id, $dryRun, &$result): void {
                    $data = $this->repository->load($id);
                    if ($data['state'] === 'promoting' || ($data['state'] === 'committed' && ($data['retained'] ?? false))) {
                        $result['uncertain']++;

                        return;
                    }
                    if ($data['expires'] > time() && $data['state'] !== 'abandoned') {
                        return;
                    }
                    if (! $dryRun) {
                        // This only touches the staging namespace. No permanent receipt is deleted.
                        $this->repository->removePayload($id);
                        $this->repository->removeManifest($id);
                    }
                    $result['removed']++;
                });
            } catch (Throwable) {
                $result['uncertain']++;
            }
        }
        // Payloads whose manifest write failed or whose process crashed before creating it.
        foreach ($this->repository->candidates('payloads') as $path) {
            if ($result['removed'] >= $this->repository->options->pruneBatchSize) {
                break;
            }
            $id = pathinfo($path, PATHINFO_FILENAME);
            if (! preg_match('/^[a-f0-9]{32}$/D', $id)) {
                continue;
            }
            $result['examined']++;
            try {
                $this->repository->locked($id, function () use ($id, $dryRun, &$result): void {
                    $disk = Storage::disk($this->repository->options->disk);
                    if ($disk->exists($this->repository->manifestPath($id))
                        || $disk->lastModified($this->repository->payloadPath($id)) + $this->repository->options->ttlSeconds > time()) {
                        return;
                    }
                    if (! $dryRun) {
                        $this->repository->removePayload($id);
                    }
                    $result['removed']++;
                });
            } catch (Throwable) {
                $result['uncertain']++;
            }
        }

        return $result;
    }

    /** @return array{owner: string, state: string, expires: int, target: string, name: string, hash: string, record_type: string|null, record_key: string|null, receipt?: array<string, mixed>, retained?: bool} */
    private function owned(string $id, string $owner): array
    {
        $data = $this->repository->load($id);
        if (! hash_equals($data['owner'], $this->owner($owner))) {
            throw new MediaFailure('denied', 'Media lease owner mismatch.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function restore(array $data, MediaWrite $intent): MediaReceipt
    {
        if (! isset($data['receipt']) || ! is_array($data['receipt'])) {
            throw new MediaFailure('stage_ambiguous', 'Interrupted promotion has no durable receipt; reconcile before retry.');
        }
        $receipt = ReceiptCodec::decode($data['receipt']);
        $this->validateReceipt($data, $intent, $receipt);
        $adapter = $this->registry->adapter($receipt->adapter);

        return $adapter instanceof MediaReceiptResolver ? $adapter->restore($receipt, $intent) : $receipt;
    }

    /** @param array<string, mixed> $data */
    private function validateReceipt(array $data, MediaWrite $intent, MediaReceipt $receipt): void
    {
        if ($receipt->adapter !== $intent->adapter || $receipt->operationId !== $intent->operationId
            || ! is_string($data['hash'] ?? null) || ! hash_equals($data['hash'], $receipt->file->sha256)) {
            throw new MediaFailure('stage_corrupt', 'Durable receipt does not belong to this media lease.');
        }
    }

    /** @param array<string, mixed> $data */
    private function complete(string $id, array $data, bool $retainPayload = false): void
    {
        $data['state'] = 'committed';
        $data['retained'] = $retainPayload;
        $data['expires'] = time() + $this->repository->options->receiptTtlSeconds;
        try {
            $this->repository->save($id, $data);
        } catch (Throwable $error) {
            $failure = new MediaFailure('stage_ambiguous', 'Stored media requires lease reconciliation.', $error);
            $failure->cleanupFailed = true;
            throw $failure;
        }
        if ($retainPayload) {
            return;
        }
        try {
            $this->repository->removePayload($id);
        } catch (Throwable $error) {
            // The permanent object and its receipt are committed. The janitor retries cleanup.
            $this->report($error);
        }
    }

    private function owner(string $owner): string
    {
        if ($owner === '' || strlen($owner) > 1024) {
            throw new MediaFailure('invalid_owner', 'Media staging requires a bounded owner identity.');
        }

        return hash('sha256', $owner);
    }

    private function target(MediaWrite $intent): string
    {
        return hash('sha256', json_encode([
            $intent->adapter, $intent->kind, $intent->disk, $intent->directory, $intent->filename,
            $intent->field, $intent->spatie?->collection, $intent->processors,
        ], JSON_THROW_ON_ERROR));
    }

    private function report(Throwable $error): void
    {
        try {
            report($error);
        } catch (Throwable) {
            // A failed logger cannot replace a storage or journal failure.
        }
    }
}
