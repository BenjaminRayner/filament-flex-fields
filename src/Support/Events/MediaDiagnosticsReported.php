<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDiagnosticReport;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaProbeReport;

/** Optional Laravel monitoring bridge. Export stable codes/status, never free-form report text. */
final readonly class MediaDiagnosticsReported implements ComponentActivity
{
    /** @var array<string, mixed> */
    private array $snapshot;

    public function __construct(MediaDiagnosticReport|MediaProbeReport $report)
    {
        $data = $report->toArray();
        $this->snapshot = ['scope' => $data['scope'], 'read_only' => $data['readOnly'],
            'operationally_verified' => false,
            'checks' => array_map(static fn (array $check): array => ['code' => $check['code'], 'status' => $check['status']], $data['checks'])];
    }

    public function toArray(): array
    {
        return $this->snapshot;
    }
}
