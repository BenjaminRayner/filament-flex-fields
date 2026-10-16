<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Data;

use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieOptions;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class MediaWrite
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $processors
     */
    public function __construct(
        public string $adapter = 'disk',
        public string $kind = 'upload',
        public ?string $disk = null,
        public string $directory = 'uploads',
        public ?string $filename = null,
        public ?string $field = null,
        public ?Model $record = null,
        public ?SpatieOptions $spatie = null,
        public ?MediaPolicy $policy = null,
        public array $metadata = [],
        public array $processors = [],
        public ?string $operationId = null,
    ) {
        foreach ([$adapter, $kind] as $identity) {
            if (! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $identity)) {
                throw new InvalidArgumentException('Invalid Media OS adapter or kind identity.');
            }
        }
        if (count($processors) > 32 || strlen(json_encode($metadata, JSON_THROW_ON_ERROR)) > 65536) {
            throw new InvalidArgumentException('Media OS extension metadata exceeds its budget.');
        }
        if ($operationId !== null && ($operationId === '' || strlen($operationId) > 255 || preg_match('/[\x00-\x1F\x7F]/', $operationId))) {
            throw new InvalidArgumentException('Invalid media operation identity.');
        }
        foreach ($processors as $processor) {
            if (! is_string($processor) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $processor)) {
                throw new InvalidArgumentException('Invalid media processor identity.');
            }
        }
    }

    public function identified(string $id): self
    {
        return new self($this->adapter, $this->kind, $this->disk, $this->directory, $this->filename, $this->field,
            $this->record, $this->spatie, $this->policy, $this->metadata, $this->processors, $id);
    }

    public function withoutProcessors(): self
    {
        return new self($this->adapter, $this->kind, $this->disk, $this->directory, $this->filename, $this->field,
            $this->record, $this->spatie, $this->policy, $this->metadata, [], $this->operationId);
    }

    public function withPolicy(MediaPolicy $policy): self
    {
        return new self($this->adapter, $this->kind, $this->disk, $this->directory, $this->filename, $this->field,
            $this->record, $this->spatie, $policy, $this->metadata, $this->processors, $this->operationId);
    }
}
