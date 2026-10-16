<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Registration;

use InvalidArgumentException;

/** Optional installation diagnostics, independent of the portable component contract. */
final readonly class RegistrationMetadata
{
    public function __construct(
        public ?DefinitionOrigin $origin = null,
        public ?ComponentDeprecation $deprecation = null,
    ) {}

    public function assertFor(string $component): void
    {
        if ($this->deprecation?->replacement === $component) {
            throw new InvalidArgumentException('A deprecated component cannot replace itself.');
        }
    }
}
