<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Translatable;

/** Storage metadata belongs to a cloned locale field, never to its template. */
final readonly class TranslatableFieldBinding
{
    public function __construct(
        public string $attribute,
        public string $locale,
        public bool $spatie,
        public ?string $outputAttribute = null,
    ) {}
}
