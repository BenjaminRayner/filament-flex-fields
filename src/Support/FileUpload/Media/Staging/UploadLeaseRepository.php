<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageRepository;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Durable authenticated bindings; no receipt or destination is accepted from browser state. */
final class UploadLeaseRepository
{
    public function __construct(private readonly Encrypter $encrypter, private readonly StageOptions $options, private readonly StageRepository $stages) {}

    /** @template T
     * @param  Closure(): T  $operation
     * @return T
     */
    public function locked(string $key, Closure $operation): mixed
    {
        return Cache::lock('fff:upload:lease:'.hash('sha256', $this->disk()."\0".$this->path($key)), $this->options->lockSeconds)
            ->block($this->options->lockWaitSeconds, $operation);
    }

    public function read(string $key): ?UploadLease
    {
        $disk = Storage::disk($this->disk());
        $path = $this->path($key);
        if (! $disk->exists($path)) {
            return null;
        }
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new MediaFailure('stage_missing', 'Upload lease is unavailable.');
        }
        try {
            $encrypted = stream_get_contents($stream, 262145);
        } finally {
            fclose($stream);
        }
        try {
            if (! is_string($encrypted) || strlen($encrypted) > 262144) {
                throw new MediaFailure('stage_corrupt', 'Upload lease exceeds its budget.');
            }
            $json = $this->encrypter->decrypt($encrypted, false);
            $data = is_string($json) ? json_decode($json, true, 32, JSON_THROW_ON_ERROR) : null;
            if (! is_array($data)) {
                throw new MediaFailure('stage_corrupt', 'Invalid upload lease.');
            }
            $lease = UploadLease::decode($data);
            if (! hash_equals($key, $lease->key)) {
                throw new MediaFailure('stage_corrupt', 'Upload lease does not match its binding.');
            }

            return $lease;
        } catch (Throwable $error) {
            throw new MediaFailure('stage_corrupt', 'Upload lease cannot be authenticated.', $error);
        }
    }

    /** Caller holds the binding lock for every read-modify-write. */
    public function write(UploadLease $lease): void
    {
        $encrypted = $this->encrypter->encrypt(json_encode($lease->encode(), JSON_THROW_ON_ERROR), false);
        if (strlen($encrypted) > 262144 || ! Storage::disk($this->disk())->put($this->path($lease->key), $encrypted, ['visibility' => 'private'])) {
            throw new MediaFailure('stage_write_failed', 'Unable to persist upload lease.');
        }
    }

    public function delete(string $key): void
    {
        if (! Storage::disk($this->disk())->delete($this->path($key))) {
            throw new MediaFailure('cleanup_failed', 'Unable to remove upload binding.');
        }
    }

    /** @return array{examined: int, removed: int, uncertain: int} */
    public function prune(bool $dryRun = false): array
    {
        $result = ['examined' => 0, 'removed' => 0, 'uncertain' => 0];
        $disk = Storage::disk($this->disk());
        if (! $disk instanceof FilesystemAdapter) {
            throw new MediaFailure('stage_storage_unsupported', 'Upload leases require a Flysystem-backed disk.');
        }
        foreach ($disk->getDriver()->listContents($this->directory(), false) as $entry) {
            if ($result['removed'] >= $this->options->pruneBatchSize) {
                break;
            }
            $key = pathinfo($entry->path(), PATHINFO_FILENAME);
            if (! $entry->isFile() || ! preg_match('/^[a-f0-9]{64}$/D', $key)) {
                continue;
            }
            $result['examined']++;
            try {
                $this->locked($key, function () use ($key, $dryRun, &$result): void {
                    $lease = $this->read($key);
                    if ($lease === null || $lease->expires > time()) {
                        return;
                    }
                    if ($lease->result !== null && ! $lease->accepted && ! $lease->removed) {
                        // A stopped process may have committed DB state. TTL is never deletion authority.
                        $result['uncertain']++;

                        return;
                    }
                    if ($lease->id !== null && ! $lease->accepted && ! $lease->removed) {
                        // A core journal can be promoting before the field receipt was persisted.
                        // Missing/corrupt journals remain uncertain, never proof that no write occurred.
                        $stage = $this->stages->load($lease->id);
                        if ($stage['state'] === 'promoting' || ($stage['state'] === 'committed' && ($stage['retained'] ?? false))) {
                            $result['uncertain']++;

                            return;
                        }
                    }
                    if (! $dryRun && ! Storage::disk($this->disk())->delete($this->path($key))) {
                        throw new MediaFailure('cleanup_failed', 'Unable to remove expired upload binding.');
                    }
                    $result['removed']++;
                });
            } catch (Throwable) {
                $result['uncertain']++;
            }
        }

        return $result;
    }

    private function disk(): string
    {
        return (string) config('filament-flex-fields.media_capture.uploads.manifest_disk', 'local');
    }

    private function directory(): string
    {
        return MediaAddress::path((string) config('filament-flex-fields.media_capture.uploads.lease_directory', 'media-os-upload-leases'));
    }

    private function path(string $key): string
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new MediaFailure('invalid_lease', 'Invalid upload lease key.');
        }

        return $this->directory().'/'.$key.'.upload';
    }
}
