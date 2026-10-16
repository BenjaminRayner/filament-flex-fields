<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Compliance;

final class ComplianceState
{
    /** @var array<string, 'pending'|'pass'|'fail'> */
    public array $overrides = [];
}
