<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Core;

use Illuminate\Contracts\Queue\ShouldQueue;

final class WorkerJob implements ShouldQueue
{
    public function __construct(public readonly int $iteration) {}

    public function handle(): void
    {
        ScopeScenario::run($this->iteration);
    }
}
