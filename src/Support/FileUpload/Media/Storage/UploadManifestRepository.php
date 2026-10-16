<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Private authenticated manifests, bounded reads and request-scoped memoization. */
final class UploadManifestRepository
{
    /** @var array<string, UploadManifest|null> */
    private array $cache = [];

    public function __construct(private readonly Encrypter $encrypter) {}

    public function read(string $disk, string $path): ?UploadManifest
    {
        $key = $this->key($disk, $path);
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $storage = Storage::disk($this->disk());
        if (! $storage->exists($key)) {
            return $this->remember($key, null);
        }
        $stream = $storage->readStream($key);
        if (! is_resource($stream)) {
            throw new MediaFailure('manifest_read_failed', 'Unable to read upload receipts.');
        }
        try {
            $encrypted = stream_get_contents($stream, 262145);
        } finally {
            fclose($stream);
        }
        if (! is_string($encrypted) || strlen($encrypted) > 262144) {
            throw new MediaFailure('manifest_invalid', 'Upload receipts exceed their budget.');
        }
        try {
            $json = $this->encrypter->decrypt($encrypted, false);
            if (! is_string($json)) {
                throw new MediaFailure('manifest_invalid', 'Invalid upload receipts.');
            }
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new MediaFailure('manifest_invalid', 'Invalid upload receipts.');
            }
            $manifest = UploadManifest::decode($data);
        } catch (Throwable $error) {
            throw new MediaFailure('manifest_invalid', 'Upload receipts cannot be authenticated or decoded.', $error);
        }
        $primary = $manifest->primaryReceipt();
        if ($primary->disk !== $disk || $primary->identifier !== $path) {
            throw new MediaFailure('manifest_invalid', 'Upload receipts do not match their object.');
        }

        return $this->remember($key, $manifest);
    }

    public function write(UploadManifest $manifest): void
    {
        $primary = $manifest->primaryReceipt();
        $key = $this->key($primary->disk, $primary->identifier);
        $json = json_encode($manifest->encode(), JSON_THROW_ON_ERROR);
        $encrypted = $this->encrypter->encrypt($json, false);
        Cache::lock('fff:upload:manifest:'.hash('sha256', $this->disk()."\0".$key), 300)->block(10, function () use ($key, $encrypted): void {
            $disk = Storage::disk($this->disk());
            if ($disk->exists($key)) {
                throw new MediaFailure('manifest_exists', 'Upload receipts already exist for this object.');
            }
            if (strlen($encrypted) > 262144 || ! $disk->put($key, $encrypted, ['visibility' => 'private'])) {
                throw new MediaFailure('manifest_write_failed', 'Unable to persist upload receipts.');
            }
        });
        $this->remember($key, $manifest);
    }

    public function delete(string $disk, string $path): void
    {
        $key = $this->key($disk, $path);
        if (! Storage::disk($this->disk())->delete($key)) {
            throw new MediaFailure('manifest_delete_failed', 'Unable to remove upload receipts.');
        }
        $this->remember($key, null);
    }

    private function disk(): string
    {
        return (string) config('filament-flex-fields.media_capture.uploads.manifest_disk', 'local');
    }

    private function key(string $disk, string $path): string
    {
        MediaAddress::path($path);
        $prefix = MediaAddress::path((string) config('filament-flex-fields.media_capture.uploads.manifest_directory', 'media-os-upload-receipts'));
        $hash = hash('sha256', $disk."\0".$path);

        return $prefix.'/'.substr($hash, 0, 2).'/'.$hash.'.receipt';
    }

    private function remember(string $key, ?UploadManifest $manifest): ?UploadManifest
    {
        if (count($this->cache) >= 256) {
            array_shift($this->cache);
        }

        return $this->cache[$key] = $manifest;
    }
}
