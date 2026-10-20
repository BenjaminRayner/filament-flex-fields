<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Illuminate\Console\Scheduling\Schedule;

final class MediaDoctorEnvironment
{
    public static function configure(): void
    {
        config()->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('d', 32)),
            'app.cipher' => 'AES-256-CBC',
            'filesystems.disks.doctor-private' => ['driver' => 'local', 'root' => '/diagnostic-private-storage', 'visibility' => 'private'],
            'filament-flex-fields.media_capture.disk' => 'doctor-private',
            'filament-flex-fields.media_capture.visibility' => 'private',
            'filament-flex-fields.media_capture.max_bytes' => 20000000,
            'filament-flex-fields.media_capture.min_bytes' => 0,
            'filament-flex-fields.media_capture.require_virus_scan' => false,
            'filament-flex-fields.media_capture.scan_before_persist' => true,
            'filament-flex-fields.media_capture.scan_after_persist' => true,
            'filament-flex-fields.media_capture.staging' => [
                'disk' => 'doctor-private', 'directory' => 'stages', 'ttl_seconds' => 86400,
                'receipt_ttl_seconds' => 604800, 'lock_seconds' => 300, 'lock_wait_seconds' => 10,
                'prune_batch_size' => 1000, 'schedule_enabled' => true,
            ],
            'filament-flex-fields.media_capture.uploads.manifest_disk' => 'doctor-private',
            'filament-flex-fields.media_capture.uploads.manifest_directory' => 'receipts',
            'filament-flex-fields.media_capture.uploads.lease_directory' => 'leases',
            'filament-flex-fields.media_capture.images.variants' => 16,
            'filament-flex-fields.media_capture.images.queue.journal_disk' => 'doctor-private',
            'filament-flex-fields.media_capture.images.queue.journal_directory' => 'journals',
            'filament-flex-fields.media_capture.images.queue.prune_schedule_enabled' => true,
            'filament-flex-fields.media_capture.retention.schedule_enabled' => true,
            'filament-flex-fields.media_capture.retention.schedule' => 'daily',
            'cache.default' => 'doctor-locks',
            'cache.stores.doctor-locks' => ['driver' => 'file', 'path' => '/diagnostic-locks'],
            'queue.default' => 'doctor-worker',
            'queue.connections.doctor-worker' => ['driver' => 'database', 'retry_after' => 120],
        ]);
        app()->forgetInstance(Schedule::class);
    }
}
