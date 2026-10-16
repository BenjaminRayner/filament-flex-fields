<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images\Queue;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\ReceiptCodec;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Authenticated, bounded, server-owned jobs; independent of browser state and upload manifests. */
final class ConversionJournal
{
    public function __construct(private readonly Encrypter $encrypter) {}

    /** @return array<string, mixed>|null */
    public function read(string $id): ?array
    {
        return $this->update($id, static fn (?array $state): ?array => $state, false);
    }

    /** @param Closure(?array<string, mixed>): ?array<string, mixed> $callback
     * @return array<string, mixed>|null
     */
    public function update(string $id, Closure $callback, bool $write = true): ?array
    {
        $path = $this->path($id);

        return Cache::lock('fff:images:journal:'.$id, 30)->block(10, function () use ($id, $path, $callback, $write): ?array {
            $disk = Storage::disk($this->disk());
            $state = null;
            if ($disk->exists($path)) {
                $stream = $disk->readStream($path);
                if (! is_resource($stream)) {
                    throw new MediaFailure('conversion_journal_unavailable', 'Cannot read conversion journal.');
                }
                try {
                    $encrypted = stream_get_contents($stream, 524289);
                } finally {
                    fclose($stream);
                }
                if (! is_string($encrypted) || strlen($encrypted) > 524288) {
                    throw new MediaFailure('conversion_journal_invalid', 'Conversion journal exceeds its budget.');
                }
                $json = $this->encrypter->decrypt($encrypted, false);
                $state = is_string($json) ? json_decode($json, true, 32, JSON_THROW_ON_ERROR) : null;
                if (! is_array($state) || ($state['version'] ?? null) !== 1 || ($state['id'] ?? null) !== $id
                    || ! in_array($state['status'] ?? null, ['awaiting_save', 'queued', 'processing', 'retrying', 'failed', 'ready', 'cancelled'], true)
                    || ! is_array($state['original'] ?? null) || ! is_array($state['variants'] ?? null) || ! is_array($state['attempts'] ?? null)
                    || count($state['variants']) > 64 || count($state['attempts']) > 64) {
                    throw new MediaFailure('conversion_journal_invalid', 'Invalid conversion journal.');
                }
            }
            $next = $callback($state);
            if ($write && $next !== null) {
                $next['updated_at'] = Date::now()->getTimestamp();
                $encrypted = $this->encrypter->encrypt(json_encode($next, JSON_THROW_ON_ERROR), false);
                if (strlen($encrypted) > 524288) {
                    throw new MediaFailure('conversion_journal_write_failed', 'Conversion journal exceeds its budget.');
                }
                // Replace through a private sibling: interruption cannot truncate the previous journal.
                // The deterministic sibling is bounded to one per identity and reused after a crash.
                $pending = $path.'.next';
                if (! $disk->put($pending, $encrypted, ['visibility' => 'private']) || ! $disk->move($pending, $path)) {
                    throw new MediaFailure('conversion_journal_write_failed', 'Cannot persist conversion journal.');
                }
            }

            return $next;
        });
    }

    /** Retain active/failed/unremoved originals; never expire an in-flight journal by age alone. */
    public function pruneCancelled(int $olderThan, int $limit = 1000): int
    {
        if ($olderThan > Date::now()->getTimestamp() || $limit < 1 || $limit > 10000) {
            throw new InvalidArgumentException('Invalid conversion journal prune bounds.');
        }
        $disk = Storage::disk($this->disk());
        if (! $disk instanceof FilesystemAdapter) {
            throw new MediaFailure('conversion_journal_unavailable', 'Journal retention requires a Flysystem-backed disk.');
        }
        $directory = dirname($this->path('00000000-0000-0000-0000-000000000000'));
        $deleted = 0;
        foreach ($disk->getDriver()->listContents($directory, false) as $entry) {
            if ($deleted >= $limit) {
                break;
            }
            if (! $entry->isFile() || ! preg_match('/([a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12})\.json\.enc$/D', $entry->path(), $match)) {
                continue;
            }
            $id = $match[1];
            $lock = Cache::lock('fff:images:worker:'.$id, 30);
            if (! $lock->get()) {
                continue;
            }
            try {
                $this->update($id, function (?array $state) use ($olderThan, $id, $disk, &$deleted): ?array {
                    if ($state === null || $state['status'] !== 'cancelled' || ($state['updated_at'] ?? PHP_INT_MAX) >= $olderThan) {
                        return $state;
                    }
                    foreach ([$state['original'], ...array_values($state['attempts']), ...array_values($state['variants'])] as $data) {
                        $receipt = ReceiptCodec::decode($data);
                        if (Storage::disk($receipt->disk)->exists($receipt->path)) {
                            return $state;
                        }
                    }
                    $path = $this->path($id);
                    if ($disk->delete($path.'.next') && $disk->delete($path)) {
                        $deleted++;
                    }

                    return null;
                }, false);
            } finally {
                $lock->release();
            }
        }

        return $deleted;
    }

    private function disk(): string
    {
        return (string) config('filament-flex-fields.media_capture.images.queue.journal_disk', 'local');
    }

    private function path(string $id): string
    {
        if (! preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D', $id)) {
            throw new MediaFailure('conversion_identity_invalid', 'Invalid conversion identity.');
        }
        $directory = MediaAddress::path((string) config('filament-flex-fields.media_capture.images.queue.journal_directory', 'media-os-conversions'));

        return $directory.'/'.$id.'.json.enc';
    }
}
