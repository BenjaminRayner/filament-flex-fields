<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Runtime;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAccessPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaProcessor;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaScanner;
use Closure;

final class MediaExtensionState
{
    /** @var array<string, Closure(): MediaAdapter> */
    public array $adapters = [];

    /** @var array<string, Closure(): MediaProcessor> */
    public array $processors = [];

    /** @var array<string, Closure(): MediaScanner> */
    public array $scanners = [];

    /** @var array<string, Closure(): MediaAccessPolicy> */
    public array $accessPolicies = [];

    public int $revision = 0;
}
