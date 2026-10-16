<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

final readonly class BarcodeCaptured implements ComponentActivity
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $field,
        public string $value,
        public ?string $format,
        public array $context = [],
    ) {}

    public function toArray(): array
    {
        return array_replace($this->context, [
            'field' => $this->field,
            'value' => $this->value,
            'format' => $this->format,
        ]);
    }
}
