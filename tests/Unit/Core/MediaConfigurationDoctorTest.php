<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaConfigurationDoctor;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDoctorOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;
use Bjanczak\FilamentFlexFields\Tests\Support\MediaDoctorEnvironment;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(fn () => MediaDoctorEnvironment::configure());

it('finds a controlled configuration fault and accepts its correction', function (array $change, string $code): void {
    config()->set($change);
    $options = new MediaDoctorOptions(queued: true, customEncoder: true);
    $result = app(MediaConfigurationDoctor::class)->inspect($options)->toArray();
    $fault = array_values(array_filter($result['checks'], fn ($check) => $check['code'] === $code));

    expect($result['configurationValid'])->toBeFalse()
        ->and($fault)->not->toBeEmpty()
        ->and($fault[0]['status'])->toBe('failed')
        ->and($fault[0]['impact'])->not->toBeEmpty()
        ->and($fault[0]['action'])->not->toBeEmpty();

    MediaDoctorEnvironment::configure();
    $fixed = app(MediaConfigurationDoctor::class)->inspect($options)->toArray();
    expect($fixed['configurationValid'])->toBeTrue()
        ->and($fixed['counts']['failed'])->toBe(0);
})->with([
    'missing original disk' => [['filament-flex-fields.media_capture.disk' => 'missing'], 'original_disk_invalid'],
    'read-only storage' => [['filesystems.disks.doctor-private.read-only' => true], 'original_disk_read_only'],
    'public recovery storage' => [['filesystems.disks.doctor-private.visibility' => 'public'], 'staging_disk_public'],
    'missing receipts disk' => [['filament-flex-fields.media_capture.uploads.manifest_disk' => 'missing'], 'receipts_disk_invalid'],
    'missing root' => [['filesystems.disks.doctor-private.root' => ''], 'original_disk_config_invalid'],
    'missing S3 bucket' => [['filesystems.disks.doctor-private' => ['driver' => 's3', 'bucket' => '']], 'original_disk_config_invalid'],
    'scoped disk cycle' => [['filesystems.disks.doctor-private' => ['driver' => 'scoped', 'disk' => 'doctor-private', 'prefix' => 'safe']], 'original_disk_invalid'],
    'unsafe scoped prefix' => [['filesystems.disks.doctor-private' => ['driver' => 'scoped', 'disk' => 'local', 'prefix' => '../private']], 'original_disk_prefix_invalid'],
    'invalid stage expiry' => [['filament-flex-fields.media_capture.staging.ttl_seconds' => 1], 'staging_options_invalid'],
    'invalid stage lock' => [['filament-flex-fields.media_capture.staging.lock_wait_seconds' => 301], 'staging_options_invalid'],
    'manifest traversal' => [['filament-flex-fields.media_capture.uploads.manifest_directory' => '../private'], 'uploads_manifest_directory_invalid'],
    'lease URL' => [['filament-flex-fields.media_capture.uploads.lease_directory' => 'https://private.test'], 'uploads_lease_directory_invalid'],
    'invalid encryption key' => [['app.key' => 'invalid-secret-key'], 'recovery_encryption'],
    'invalid byte limits' => [['filament-flex-fields.media_capture.min_bytes' => 30000000], 'media_policy_invalid'],
    'invalid image limits' => [['filament-flex-fields.media_capture.images.variants' => 100], 'image_limits_invalid'],
    'synchronous queue' => [['queue.connections.doctor-worker.driver' => 'sync'], 'conversion_queue_invalid'],
    'overlapping retry' => [['queue.connections.doctor-worker.retry_after' => 60], 'conversion_queue_invalid'],
    'malformed retry' => [['queue.connections.doctor-worker.retry_after' => 'not-a-number'], 'queue_retry_window'],
    'process-local locks' => [['cache.stores.doctor-locks.driver' => 'array'], 'cache_locks_invalid'],
    'missing file lock path' => [['cache.stores.doctor-locks.path' => ''], 'cache_path_invalid'],
    'missing required scanner' => [['filament-flex-fields.media_capture.require_virus_scan' => true], 'required_scanner'],
    'disabled mandatory scan' => [['filament-flex-fields.media_capture.require_virus_scan' => true, 'filament-flex-fields.media_capture.scan_after_persist' => false], 'media_policy_invalid'],
    'invalid schedule flag' => [['filament-flex-fields.media_capture.staging.schedule_enabled' => 'yes'], 'staging_schedule_invalid'],
    'unsupported schedule frequency' => [['filament-flex-fields.media_capture.retention.schedule' => '* * * * *'], 'capture_schedule_frequency'],
    'missing journal disk' => [['filament-flex-fields.media_capture.images.queue.journal_disk' => 'missing'], 'journal_disk_invalid'],
    'unsafe journal directory' => [['filament-flex-fields.media_capture.images.queue.journal_directory' => '/private'], 'journal_directory_invalid'],
]);

it('detects missing real schedule registration and its repair', function (): void {
    app()->instance(Schedule::class, new Schedule);
    $options = new MediaDoctorOptions(queued: true, customEncoder: true);
    $broken = app(MediaConfigurationDoctor::class)->inspect($options)->toArray();
    expect($broken['configurationValid'])->toBeFalse()
        ->and(array_column(array_filter($broken['checks'], fn ($row) => $row['status'] === 'failed'), 'code'))
        ->toContain('staging_schedule_registered', 'capture_schedule_registered', 'conversions_schedule_registered');

    app()->forgetInstance(Schedule::class);
    expect(app(MediaConfigurationDoctor::class)->inspect($options)->toArray()['configurationValid'])->toBeTrue();
});

it('agrees with the actual conversion queue rejection rules', function (): void {
    config()->set('queue.connections.doctor-worker.retry_after', 30);
    expect(fn () => (new ConversionQueueOptions(timeout: 60))->connectionName())->toThrow(InvalidArgumentException::class);
    expect(app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions(queued: true, customEncoder: true))->toArray()['configurationValid'])->toBeFalse();
});

it('does not treat an SQS retry_after setting as a verified visibility timeout', function (): void {
    config()->set('queue.connections.doctor-worker.driver', 'sqs');
    $result = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions(queued: true, customEncoder: true))->toArray();
    expect($result['configurationValid'])->toBeTrue()
        ->and(array_column($result['checks'], 'code'))->toContain('sqs_visibility_unverified')
        ->not->toContain('queue_retry_window')
        ->and($result['operationallyVerified'])->toBeFalse();
});

it('does not fail disabled built-in schedules or pretend their replacements were checked', function (): void {
    config()->set('filament-flex-fields.media_capture.staging.schedule_enabled', false);
    $result = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions)->toArray();
    expect($result['configurationValid'])->toBeTrue()
        ->and(array_values(array_filter($result['checks'], fn ($row) => $row['code'] === 'staging_schedule_external'))[0]['status'])->toBe('unverified');
});

it('does not invent a verified retry window when it is not explicitly configured', function (): void {
    config()->set('queue.connections.doctor-worker.retry_after', null);
    $result = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions(queued: true, customEncoder: true))->toArray();
    $check = array_values(array_filter($result['checks'], fn ($row) => $row['code'] === 'queue_retry_window'))[0];
    expect($result['configurationValid'])->toBeTrue()->and($check['status'])->toBe('unverified');
});

it('does not resolve custom filesystem or queue drivers during configuration inspection', function (): void {
    config()->set('filesystems.disks.doctor-private.driver', 'custom-private-driver');
    config()->set('queue.connections.doctor-worker.driver', 'custom-private-driver');
    $result = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions(queued: true, customEncoder: true))->toArray();
    $statuses = array_column($result['checks'], 'status', 'code');
    expect($result['configurationValid'])->toBeTrue()
        ->and($statuses['original_disk_declared'])->toBe('unverified')
        ->and($statuses['queue_driver_unverified'])->toBe('unverified');
});

it('does not validate an unused capture schedule frequency when that schedule is disabled', function (): void {
    config()->set('filament-flex-fields.media_capture.retention.schedule_enabled', false);
    config()->set('filament-flex-fields.media_capture.retention.schedule', 'application-managed');
    expect(app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions)->toArray()['configurationValid'])->toBeTrue();
});

it('does not accept a shell command that only echoes a cleanup task name', function (): void {
    $schedule = app(Schedule::class);
    $events = $schedule->events();
    $replacement = new Schedule;
    foreach ($events as $event) {
        if (is_string($event->command) && ! str_contains($event->command, 'flex-fields:prune-media-staging')) {
            $replacement->exec($event->command)->daily();
        }
    }
    $replacement->exec('echo flex-fields:prune-media-staging')->hourly();
    app()->instance(Schedule::class, $replacement);
    $result = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions)->toArray();
    expect($result['configurationValid'])->toBeFalse()
        ->and(array_column(array_filter($result['checks'], fn ($row) => $row['status'] === 'failed'), 'code'))->toContain('staging_schedule_registered');
});

it('detects duplicate native cleanup tasks', function (): void {
    app(Schedule::class)->command('flex-fields:prune-media-staging')->daily();
    $result = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions)->toArray();
    expect($result['configurationValid'])->toBeFalse()
        ->and(array_column(array_filter($result['checks'], fn ($row) => $row['status'] === 'failed'), 'code'))->toContain('staging_schedule_duplicate');
});

it('supports public originals while retaining private recovery disks', function (): void {
    config()->set('filesystems.disks.doctor-public', ['driver' => 'local', 'root' => '/diagnostic-public-images', 'visibility' => 'public']);
    config()->set('filament-flex-fields.media_capture.disk', 'doctor-public');
    $options = new MediaDoctorOptions;
    $private = app(MediaConfigurationDoctor::class)->inspect($options)->toArray();
    expect($private['configurationValid'])->toBeFalse();

    config()->set('filament-flex-fields.media_capture.visibility', 'public');
    expect(app(MediaConfigurationDoctor::class)->inspect($options)->toArray()['configurationValid'])->toBeTrue();
});

it('bounds a long scoped disk chain without resolving any adapter', function (): void {
    for ($index = 0; $index < 9; $index++) {
        config()->set('filesystems.disks.chain-'.$index, ['driver' => 'scoped', 'disk' => 'chain-'.($index + 1), 'prefix' => 'safe']);
    }
    config()->set('filesystems.disks.chain-9', ['driver' => 'local', 'root' => '/private']);
    config()->set('filament-flex-fields.media_capture.disk', 'chain-0');
    $report = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions)->toArray();
    expect($report['configurationValid'])->toBeFalse()
        ->and(array_column($report['checks'], 'code'))->toContain('original_disk_depth');
});

it('identifies the exact failed configuration boundary without exporting its value', function (array $change, string $code, string $option): void {
    config()->set($change);
    $report = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions(queued: true, customEncoder: true))->toArray();
    $failure = array_values(array_filter($report['checks'], fn ($row) => $row['code'] === $code));
    expect($failure)->toHaveCount(1)
        ->and($failure[0]['status'])->toBe('failed')
        ->and($failure[0]['action'])->toContain($option)
        ->and(json_encode($report))->not->toContain('private-traversal-marker');
})->with([
    'maximum size' => [['filament-flex-fields.media_capture.max_bytes' => -1], 'media_max_bytes_invalid', 'media_capture.max_bytes'],
    'size ordering' => [['filament-flex-fields.media_capture.min_bytes' => 30000000], 'media_byte_order_invalid', 'media_capture.min_bytes'],
    'staging traversal' => [['filament-flex-fields.media_capture.staging.directory' => '../private-traversal-marker'], 'staging_directory_invalid', 'relative directory'],
    'variant count' => [['filament-flex-fields.media_capture.images.variants' => 0], 'images_variants_invalid', 'media_capture.images.variants'],
    'durable driver' => [['queue.connections.doctor-worker.driver' => 'sync'], 'queue_driver_not_durable', 'driver'],
    'retry window' => [['queue.connections.doctor-worker.retry_after' => 30], 'queue_retry_window', 'retry_after'],
]);
