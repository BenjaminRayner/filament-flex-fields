<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\MediaOperationObserved;
use Bjanczak\FilamentFlexFields\Support\Events\UploadFailed;
use Bjanczak\FilamentFlexFields\Support\Events\UploadSucceeded;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Events\MediaDeleted;
use Bjanczak\FilamentFlexFields\Support\Media\Events\MediaRejected;
use Bjanczak\FilamentFlexFields\Support\Media\Events\MediaStored;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaDefaults;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Security\RejectedMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Security\ScannerHub;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Throwable;

/** One scoped media transaction coordinator, independent of component HTML/state. */
final class MediaOs
{
    public function __construct(
        private readonly MediaRegistry $registry,
        private readonly MediaDefaults $defaults,
        private readonly ScannerHub $scanners,
        private readonly RejectedMedia $rejected,
        private readonly Dispatcher $events,
        private readonly ActivityDispatcher $activity,
    ) {}

    /** @param (Closure(MediaReceipt, MediaWrite): void)|null $finalize */
    public function store(MediaSource $source, MediaWrite $intent, ?Closure $finalize = null): MediaReceipt
    {
        $intent = $intent->identified($intent->operationId ?? (string) Str::uuid());
        $policy = $intent->policy ?? $this->defaults->policy();
        $intent = $intent->withPolicy($policy);
        $prepared = null;
        $receipt = null;
        try {
            $this->authorize('store', $intent);
            $adapter = $this->registry->adapter($intent->adapter);
            $prepared = $this->inspect($source, $intent);
            $receipt = $adapter->write($prepared, $intent);
            if ($receipt->adapter !== $intent->adapter || $receipt->operationId !== $intent->operationId) {
                // An unrelated receipt provides no authority to delete another operation's object.
                $receipt = null;
                $failure = new MediaFailure('invalid_receipt', 'Media adapter returned an unrelated receipt.');
                $failure->cleanupFailed = true;
                throw $failure;
            }
            $this->verify($receipt, $intent, $policy->verifyIntegrity);
            if ($finalize !== null) {
                try {
                    $finalize($receipt, $intent);
                } catch (MediaFailure $error) {
                    throw $error;
                } catch (Throwable $error) {
                    throw new MediaFailure('finalizer_failed', 'Media finalizer failed.', $error);
                }
                $this->verify($receipt, $intent, $policy->verifyIntegrity);
            }
            $adapter->commit($receipt, $intent);
        } catch (Throwable $error) {
            $cleanupFailed = $receipt !== null && ! $this->compensate($receipt, $intent, $policy);
            $failure = $error instanceof MediaFailure ? $error : new MediaFailure('operation_failed', 'Media operation failed.', $error);
            $failure->cleanupFailed = $failure->cleanupFailed || $cleanupFailed;
            $cleanupFailed = $failure->cleanupFailed;
            $this->notify(new MediaRejected($intent, $failure->reason, $cleanupFailed));
            $this->observe(false, $intent, $failure->reason);
            throw $failure;
        } finally {
            $prepared?->close();
        }
        $this->observe(true, $intent);
        $this->notify(new MediaStored($receipt, $intent));

        return $receipt;
    }

    /** Caller owns the artifact and must close it; processors and scanners share the same measured bytes. */
    public function inspect(MediaSource $source, MediaWrite $intent): PreparedMedia
    {
        $this->authorize('inspect', $intent);
        $policy = $intent->policy ?? $this->defaults->policy();
        $file = PreparedMedia::prepare($source, $policy);
        try {
            foreach ($intent->processors as $processor) {
                $next = $this->registry->processor($processor)->process($file, $intent);
                if ($next !== $file) {
                    $file->close();
                    $file = $next;
                }
                $policy->validate($file->file);
            }
            $this->scanners->scan($file, $intent, $policy, 'before');

            return $file;
        } catch (Throwable $error) {
            $file->close();
            throw $error;
        }
    }

    public function verify(MediaReceipt $receipt, MediaWrite $intent, bool $integrity = true): void
    {
        $this->authorize('verify', $intent, $receipt);
        $policy = $intent->policy ?? $this->defaults->policy();
        $adapter = $this->registry->adapter($receipt->adapter);
        $stream = $adapter->read($receipt);
        try {
            $file = PreparedMedia::prepare(MediaSource::stream($stream, $receipt->file->originalName), $policy);
            try {
                if ($integrity && ! hash_equals($receipt->file->sha256, $file->file->sha256)) {
                    throw new MediaFailure('integrity_mismatch', 'Stored media differs from prepared bytes.');
                }
                $this->scanners->scan($file, $intent, $policy, 'after');
            } finally {
                $file->close();
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function delete(MediaReceipt $receipt, MediaWrite $intent): void
    {
        $this->authorize('delete', $intent, $receipt);
        $this->registry->adapter($receipt->adapter)->delete($receipt);
        $this->notify(new MediaDeleted($receipt, $intent));
    }

    public function url(MediaReceipt $receipt, MediaWrite $intent, DateTimeInterface $expiresAt, string $conversion = ''): ?string
    {
        $this->authorize('url', $intent, $receipt);

        return $this->registry->adapter($receipt->adapter)->url($receipt, $expiresAt, $conversion);
    }

    /** Reject created artifacts even when quarantine fails; return the complete cleanup outcome. */
    public function compensate(MediaReceipt $receipt, MediaWrite $intent, ?MediaPolicy $policy = null): bool
    {
        $policy ??= $intent->policy ?? $this->defaults->policy();
        try {
            $adapter = $this->registry->adapter($receipt->adapter);
        } catch (Throwable $error) {
            $this->report($error);

            return false;
        }

        return ! $this->rejected->remove($adapter, $receipt, $policy->quarantineDisk)->cleanupFailed;
    }

    public function authorize(string $action, MediaWrite $intent, ?MediaReceipt $receipt = null): void
    {
        foreach ($this->registry->accessPolicies() as $policy) {
            $policy->authorize($action, $intent, $receipt);
        }
    }

    private function notify(object $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (Throwable $error) {
            $this->report($error);
        }
    }

    private function observe(bool $success, MediaWrite $intent, ?string $reason = null): void
    {
        $event = $success
            ? new UploadSucceeded($intent->field, $intent->adapter, $intent->kind, $intent->operationId)
            : new UploadFailed($intent->field, $intent->adapter, $reason, $intent->kind, $intent->operationId);

        $this->activity->dispatch($event);
        $parent = $intent->metadata['original_operation'] ?? $intent->operationId;
        $this->activity->dispatch(new MediaOperationObserved($success ? 'stored' : 'rejected',
            is_string($parent) ? $parent : null, $intent->operationId, reason: $reason));
    }

    private function report(Throwable $error): void
    {
        try {
            report($error);
        } catch (Throwable) {
            // Reporting infrastructure cannot replace an operation's original failure or undo a commit.
        }
    }
}
