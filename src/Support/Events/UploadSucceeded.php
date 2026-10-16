<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

final readonly class UploadSucceeded implements ComponentActivity
{
    public function __construct(
        public ?string $field,
        public string $adapter,
        public string $kind,
        public ?string $correlationId,
    ) {}

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'adapter' => $this->adapter,
            'kind' => $this->kind,
            'correlation_id' => $this->correlationId,
        ];
    }
}
