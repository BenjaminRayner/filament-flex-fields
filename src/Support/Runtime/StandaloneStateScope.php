<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

/** Internal context; each Fiber and the synchronous caller have independent state. */
final class StandaloneStateScope
{
    /** @var array<class-string, object> */
    public array $states = [];

    /** @var array<class-string, true> */
    public array $constructing = [];

    public int $depth = 0;
}
