<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

/** A notification snapshot consumed by native Laravel listeners. */
interface ComponentActivity
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
