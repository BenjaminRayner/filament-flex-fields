<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Closure;

final class MediaOperationalProbe
{
    public function __construct(
        private readonly MediaStorageProbe $storage,
        private readonly MediaLockProbe $locks,
        private readonly MediaImageProbe $images,
    ) {}

    /** @param (Closure(string): void)|null $onStart */
    public function inspect(MediaProbeOptions $options, ?Closure $onStart = null): MediaProbeReport
    {
        $report = new MediaProbeReport($options);
        $onStart?->__invoke($report->runId);
        if ($options->disk !== null) {
            $this->storage->inspect($options->disk, $report);
        }
        if ($options->lockStore !== null) {
            $this->locks->inspect($options->lockStore, $report);
        }
        if ($options->formats !== []) {
            $this->images->inspect($options->formats, $report);
        }

        return $report;
    }
}
