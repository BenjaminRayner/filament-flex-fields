<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

final class MediaStorageProbe
{
    public function __construct(private readonly Factory $filesystems) {}

    public function inspect(string $name, MediaProbeReport $report): void
    {
        try {
            $disk = $this->filesystems->disk($name);
        } catch (Throwable) {
            $report->add('storage_resolution', false);

            return;
        }
        $report->add('storage_resolution', true);
        $path = '.fff-diagnostics/'.$report->runId.'.probe';
        $payload = 'FLEX diagnostic bytes: '.bin2hex(random_bytes(32));
        try {
            if (! $report->check('storage_write', static fn (): bool => $disk->put($path, $payload, ['visibility' => 'private']) === true)) {
                return;
            }
            $report->check('storage_read', fn (): bool => $this->matches($disk, $path, $payload));
            $report->check('storage_visibility', static fn (): bool => $disk->getVisibility($path) === 'private');
        } finally {
            // A backend can write and then throw. Clean the unique target even after a failed write.
            $report->check('storage_cleanup', static fn (): bool => $disk->delete($path) === true && $disk->exists($path) === false);
        }
    }

    private function matches(Filesystem $disk, string $path, string $expected): bool
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return false;
        }
        try {
            $actual = stream_get_contents($stream, strlen($expected) + 1);

            return is_string($actual) && hash_equals($expected, $actual);
        } finally {
            fclose($stream);
        }
    }
}
