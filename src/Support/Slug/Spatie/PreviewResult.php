<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug\Spatie;

final readonly class PreviewResult
{
    public function __construct(public string $value, public bool $generated, public ?string $skipReason = null) {}
}
