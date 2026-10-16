<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Staging;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use JsonException;

/** Private server-owned manifests, never client supplied JSON or native temporary upload paths. */
final class StageRepository
{
    public function __construct(public readonly StageOptions $options) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function locked(string $id, Closure $operation): mixed
    {
        $this->validateId($id);

        return Cache::lock('fff:media:lease:'.hash('sha256', $this->options->disk.'|'.$this->options->directory.'|'.$id), $this->options->lockSeconds)
            ->block($this->options->lockWaitSeconds, $operation);
    }

    /** @param array<string, mixed> $data */
    public function save(string $id, array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        if (strlen($json) > 65536 || ! Storage::disk($this->options->disk)->put($this->manifestPath($id), $json, ['visibility' => 'private'])) {
            throw new MediaFailure('stage_write_failed', 'Unable to persist the media lease.');
        }
    }

    /** @return array{owner: string, state: string, expires: int, target: string, name: string, hash: string, record_type: string|null, record_key: string|null, receipt?: array<string, mixed>, retained?: bool} */
    public function load(string $id): array
    {
        $stream = Storage::disk($this->options->disk)->readStream($this->manifestPath($id));
        if (! is_resource($stream)) {
            throw new MediaFailure('stage_missing', 'The media lease is unavailable.');
        }
        try {
            $json = stream_get_contents($stream, 65537);
            if (! is_string($json) || strlen($json) > 65536) {
                throw new MediaFailure('stage_corrupt', 'Media lease exceeds its metadata budget.');
            }
            try {
                $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new MediaFailure('stage_corrupt', 'Invalid media lease JSON.', $error);
            }
            if (! is_array($data) || ! isset($data['owner'], $data['state'], $data['expires'], $data['target'], $data['name'], $data['hash'])
                || ! is_string($data['owner']) || ! is_string($data['state']) || ! is_int($data['expires'])
                || ! is_string($data['target']) || ! is_string($data['name']) || ! is_string($data['hash'])
                || ! preg_match('/^[a-f0-9]{64}$/D', $data['owner'])
                || ! preg_match('/^[a-f0-9]{64}$/D', $data['target'])
                || ! preg_match('/^[a-f0-9]{64}$/D', $data['hash'])
                || ! in_array($data['state'], ['pending', 'promoting', 'committed', 'abandoned'], true)
                || $data['expires'] < 1 || strlen($data['name']) > 1024
                || (isset($data['record_type']) && ! is_string($data['record_type']))
                || (isset($data['record_key']) && ! is_string($data['record_key']))
                || (isset($data['receipt']) && ! is_array($data['receipt']))
                || (isset($data['retained']) && ! is_bool($data['retained']))) {
                throw new MediaFailure('stage_corrupt', 'Invalid media lease metadata.');
            }
            $validated = ['owner' => $data['owner'], 'state' => $data['state'], 'expires' => $data['expires'],
                'target' => $data['target'], 'name' => $data['name'], 'hash' => $data['hash'],
                'record_type' => $data['record_type'] ?? null, 'record_key' => $data['record_key'] ?? null];
            if (isset($data['receipt'])) {
                $validated['receipt'] = $data['receipt'];
            }
            if (isset($data['retained'])) {
                $validated['retained'] = $data['retained'];
            }

            return $validated;
        } finally {
            fclose($stream);
        }
    }

    public function manifestPath(string $id): string
    {
        $this->validateId($id);

        return $this->options->directory.'/leases/'.$id.'.json';
    }

    public function payloadPath(string $id): string
    {
        $this->validateId($id);

        return $this->options->directory.'/payloads/'.$id.'.bin';
    }

    public function removePayload(string $id): void
    {
        if (! Storage::disk($this->options->disk)->delete($this->payloadPath($id))) {
            throw new MediaFailure('cleanup_failed', 'Unable to remove staged media.');
        }
    }

    public function removeManifest(string $id): void
    {
        if (! Storage::disk($this->options->disk)->delete($this->manifestPath($id))) {
            throw new MediaFailure('cleanup_failed', 'Unable to remove the expired media lease.');
        }
    }

    /**
     * Lazy listing does not retain filenames in memory. The janitor caps mutations,
     * not visits: active or ambiguous leases must not starve later expired files.
     *
     * @return iterable<string>
     */
    public function candidates(string $kind): iterable
    {
        $storage = Storage::disk($this->options->disk);
        if (! $storage instanceof FilesystemAdapter || ! in_array($kind, ['leases', 'payloads'], true)) {
            throw new MediaFailure('stage_list_failed', 'Staging needs a Laravel filesystem adapter.');
        }
        foreach ($storage->getDriver()->listContents($this->options->directory.'/'.$kind, false) as $entry) {
            if ($entry->isFile()) {
                yield $entry->path();
            }
        }
    }

    private function validateId(string $id): void
    {
        if (! preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new MediaFailure('invalid_lease', 'Invalid media lease identity.');
        }
    }
}
