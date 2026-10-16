<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

final readonly class UploadFailed implements ComponentActivity
{
    public function __construct(
        public ?string $field,
        public string $adapter,
        public ?string $reason,
        public ?string $kind = null,
        public ?string $correlationId = null,
        public ?string $stage = null,
        public ?string $path = null,
    ) {}

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'adapter' => $this->adapter,
            'reason' => $this->reason,
            'kind' => $this->kind,
            'correlation_id' => $this->correlationId,
            'stage' => $this->stage,
            'path' => $this->path,
        ];
    }
}
