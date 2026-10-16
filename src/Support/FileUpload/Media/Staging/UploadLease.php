<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifest;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

/** Server-owned link between a native temporary upload and its single promotion. */
final class UploadLease
{
    public function __construct(
        public readonly string $key,
        public readonly string $owner,
        public readonly string $fingerprint,
        public readonly ?string $filename,
        public int $expires,
        public ?string $id = null,
        public ?UploadManifest $result = null,
        public bool $accepted = false,
        public bool $removed = false,
    ) {}

    public function intent(MediaWrite $intent): MediaWrite
    {
        return new MediaWrite($intent->adapter, $intent->kind, $intent->disk, $intent->directory, $this->filename,
            $intent->field, $intent->record, $intent->spatie, $intent->policy,
            [...$intent->metadata, 'flex_upload_lease' => $this->key], $intent->processors, $this->id);
    }

    /** @return array<string, mixed> */
    public function encode(): array
    {
        return ['version' => 1, 'key' => $this->key, 'owner' => $this->owner, 'fingerprint' => $this->fingerprint,
            'filename' => $this->filename, 'expires' => $this->expires, 'id' => $this->id,
            'result' => $this->result?->encode(), 'accepted' => $this->accepted, 'removed' => $this->removed];
    }

    /** @param array<string, mixed> $data */
    public static function decode(array $data): self
    {
        foreach (['key', 'owner', 'fingerprint'] as $key) {
            if (! is_string($data[$key] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $data[$key])) {
                throw new MediaFailure('stage_corrupt', 'Invalid upload lease identity.');
            }
        }
        if (($data['version'] ?? null) !== 1 || ! is_int($data['expires'] ?? null) || $data['expires'] < 1
            || ! is_bool($data['accepted'] ?? null) || (isset($data['filename']) && ! is_string($data['filename']))
            || (isset($data['id']) && (! is_string($data['id']) || ! preg_match('/^[a-f0-9]{32}$/D', $data['id'])))
            || (isset($data['result']) && ! is_array($data['result']))
            || (isset($data['removed']) && ! is_bool($data['removed']))) {
            throw new MediaFailure('stage_corrupt', 'Invalid upload lease metadata.');
        }

        return new self($data['key'], $data['owner'], $data['fingerprint'], $data['filename'] ?? null,
            $data['expires'], $data['id'] ?? null, isset($data['result']) ? UploadManifest::decode($data['result']) : null, $data['accepted'], $data['removed'] ?? false);
    }
}
