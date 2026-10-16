<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

final readonly class FieldMounted implements ComponentActivity
{
    public function __construct(
        public string $field,
        public string $type,
    ) {}

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'type' => $this->type,
        ];
    }
}
