<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Staging;

final readonly class StageLease
{
    public function __construct(public string $id, public int $expiresAt) {}
}
