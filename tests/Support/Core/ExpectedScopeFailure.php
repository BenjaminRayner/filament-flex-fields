<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Core;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/** A controlled failure must reset state without generating unbounded test log output. */
final class ExpectedScopeFailure extends RuntimeException implements ShouldntReport {}
